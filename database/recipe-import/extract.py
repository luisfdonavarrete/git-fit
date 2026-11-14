"""Extract Built With Science recipe PDFs into recipes.json + photos.

Writes the files RecipeSeeder imports (see config/recipes.php). The output
is copyrighted content, so keep it out of git (storage/app/private is ignored).

Setup (macOS only, the OCR uses the built-in Vision framework):
    python3 -m venv /tmp/recipe-venv
    /tmp/recipe-venv/bin/pip install -r database/recipe-import/requirements.txt

Run (takes a few minutes for ~680 pages):
    /tmp/recipe-venv/bin/python database/recipe-import/extract.py \
        ~/Documents/"Built With Science"/recipes storage/app/private/recipe-import
    php artisan db:seed --class=RecipeSeeder

How it works: regular text comes from the PDF text layer. The bold parts
(titles, ingredient quantities, step numbers) are vector outlines rather than
text, so each recipe page is rendered and read with OCR. Versions of the same
dish ("... with Fruit") are merged using the names on each PDF's index pages.
Warnings for anything that couldn't be matched are printed at the end.
"""
import io
import json
import pathlib
import re
import sys
import unicodedata

import pypdf
import pypdfium2 as pdfium
from PIL import Image

sys.path.insert(0, str(pathlib.Path(__file__).parent))
import ocr as ocr_mod  # noqa: E402

FILES = {
    'CSMs Breakfast.pdf': 'breakfast',
    'CSMs Lunch.pdf': 'lunch',
    'CSMs Dinner.pdf': 'dinner',
    'CSMs Regular Snacks.pdf': 'snack',
    'CSMs High Protein Snacks.pdf': 'high_protein_snack',
}

LIGATURES = {'ﬁ': 'fi', 'ﬂ': 'fl', 'ﬀ': 'ff', 'ﬃ': 'ffi', 'ﬄ': 'ffl', ' ': ' ', 'º': '°'}
UNICODE_FRACTIONS = {'½': '1/2', '⅓': '1/3', '⅔': '2/3', '¼': '1/4', '¾': '3/4', '⅛': '1/8'}

warnings = []


def clean(text):
    for a, b in LIGATURES.items():
        text = text.replace(a, b)
    text = text.replace('“', '"').replace('”', '"').replace('’', "'").replace('‘', "'")
    return re.sub(r'\s+', ' ', text).strip()


def norm(text):
    text = unicodedata.normalize('NFKD', clean(text)).lower()
    return re.sub(r'[^a-z0-9]', '', text)


def slugify(text):
    text = unicodedata.normalize('NFKD', text).encode('ascii', 'ignore').decode()
    return re.sub(r'[^a-z0-9]+', '-', text.lower()).strip('-')


def page_lines(page):
    """Group text runs into lines: list of dicts {x, y, text}, top to bottom."""
    runs = []

    def visitor(text, cm, tm, fd, fs):
        if text and text.strip():
            # Effective position = text matrix combined with CTM.
            x = tm[4] * cm[0] + tm[5] * cm[2] + cm[4]
            y = tm[4] * cm[1] + tm[5] * cm[3] + cm[5]
            runs.append((x, y, text))

    page.extract_text(visitor_text=visitor)
    lines = []
    for x, y, text in runs:
        for line in lines:
            if abs(line['y'] - y) < 2 and abs(line['col_x'] - x) < 200 and (x >= 240) == (line['x'] >= 240):
                line['parts'].append((x, text))
                line['col_x'] = x
                break
        else:
            lines.append({'x': x, 'y': y, 'col_x': x, 'parts': [(x, text)]})
    for line in lines:
        line['parts'].sort()
        line['x'] = line['parts'][0][0]
        line['text'] = clean(''.join(t for _, t in line['parts']))
    return sorted(lines, key=lambda l: (-l['y'], l['x']))


def parse_minutes(text):
    m = re.search(r'(?:(\d+)\s*HOURS?)?\s*(?:(\d+)\s*MINUTES?)?', text)
    hours = int(m.group(1) or 0) if m else 0
    minutes = int(m.group(2) or 0) if m else 0
    total = hours * 60 + minutes
    return total or None


def parse_quantity(raw):
    raw = clean(raw)
    for a, b in UNICODE_FRACTIONS.items():
        raw = raw.replace(a, ' ' + b)
    # OCR drops the space in mixed numbers: "11/2" -> "1 1/2".
    raw = re.sub(r'^(\d)(\d/\d+)\b', r'\1 \2', raw)
    m = re.match(r'^(?:(\d+)\s+(\d+)/(\d+)|(\d+)/(\d+)|(\d+(?:\.\d+)?))\s*(.*)$', raw)
    if not m:
        return None, raw or None
    mixed_whole, mixed_num, mixed_den, num, den, plain, unit = m.groups()
    if mixed_whole:
        value = int(mixed_whole) + int(mixed_num) / int(mixed_den)
    elif num:
        value = int(num) / int(den)
    else:
        value = float(plain)
    return round(value, 3), (unit.strip() or None)


def split_name_note(text):
    m = re.match(r'^(.*?)\s*\((.*)\)\s*$', text)
    if m and m.group(1):
        return m.group(1).strip(), m.group(2).strip()
    return text.strip(), None


def ocr_page(pdf, index, scratch):
    path = scratch / f'ocr_{index}.png'
    pdf[index].render(scale=2.5).to_pil().save(path)
    rows = ocr_mod.ocr(str(path))
    path.unlink()
    return rows


def quantity_for(ingredient_text, ocr_rows):
    target = norm(ingredient_text)[:14]
    if not target:
        return None
    for x, y, _, line in ocr_rows:
        # Only the ingredients column (left, below the photo).
        if x > 0.36 or y > 0.6:
            continue
        if target not in norm(line):
            continue
        for k in range(len(line)):
            if norm(line[k:]).startswith(target):
                prefix = line[:k].strip()
                return prefix or None
    return None


def extract_photo(page):
    best = None
    for img in page.images:
        w, h = img.image.size
        if best is None or w * h > best[0]:
            best = (w * h, img.image)
    if best is None or best[0] < 200 * 200:
        return None
    image = best[1].convert('RGB')
    if image.width > 1200:
        image = image.resize((1200, round(image.height * 1200 / image.width)))
    buf = io.BytesIO()
    image.save(buf, 'JPEG', quality=82, optimize=True)
    return buf.getvalue()


def parse_recipe_page(lines, plain):
    body = [l for l in lines if 40 < l['y'] < 690]
    header = ' '.join(l['text'] for l in lines if l['y'] >= 690)
    left = [l for l in body if l['x'] < 240]
    right = [l for l in body if l['x'] >= 240]

    # Left column: ingredients until the calories line, then nutrition.
    ingredients, nutrition_started = [], False
    for line in left:
        if re.fullmatch(r'\d{1,4}( \d+g)?', line['text']) or line['text'].startswith(('Fiber', 'Sodium')):
            nutrition_started = True
        if nutrition_started:
            continue
        previous = ingredients[-1] if ingredients else None
        # Wrapped lines sit ~12pt below the previous line; new ingredients ~14pt.
        wrapped = previous is not None and (
            line['text'].startswith('(')
            or previous['text'].count('(') > previous['text'].count(')')
            or (previous['y'] - line['y']) < 13
        )
        if previous is not None:
            previous['y'] = line['y']
        if line['x'] < 67 and wrapped:
            ingredients[-1]['text'] += ' ' + line['text']
            ingredients[-1]['lines'] += 1
        else:
            ingredients.append({'text': line['text'], 'first': line['text'], 'indented': line['x'] >= 67, 'lines': 1, 'y': line['y']})

    m = re.search(
        r'\n(\d{1,4}) (\d+(?:\.\d+)?)g\n(\d+(?:\.\d+)?)g ?Sodium ?(\d+) ?m?g?…?\n(\d+(?:\.\d+)?)g ?Potassium ?(\d+) ?m?g?…?\nFiber ?(\d+(?:\.\d+)?)g',
        plain,
    )
    if not m:
        return None
    calories, protein, fat, sodium, carbs, _potassium, fiber = m.groups()

    directions, notes = [], []
    prev = None
    for line in right:
        is_direction = line['x'] >= 252
        bucket = directions if is_direction else notes
        gap = (prev['y'] - line['y']) if prev and prev['is_direction'] == is_direction else 999
        threshold = 15 if is_direction else 18
        if bucket and gap <= threshold:
            bucket[-1] += ' ' + line['text']
        else:
            bucket.append(line['text'])
        prev = {**line, 'is_direction': is_direction}

    return {
        'header': header,
        'calories': int(calories),
        'protein_g': float(protein),
        'fat_g': float(fat),
        'carbs_g': float(carbs),
        'fiber_g': float(fiber),
        'sodium_mg': int(sodium),
        'ingredients': ingredients,
        'directions': directions,
        'notes': notes,
    }


def mfp_title(notes):
    text = ' '.join(notes)
    m = re.search(r'searching "BWS (.+?)(?:\s*-\s*(?:\d{3}\s*-\s*\d{3}|\d{3}\s*\+|\d{3}-))?\s*"', text)
    return clean(m.group(1)).rstrip(' -') if m else None


def index_titles(plain):
    titles = []
    for line in plain.split('\n'):
        t = clean(line)
        if not t or t.startswith(('BUILT WITH SCIENCE', '= Veg')) or t.upper() == t:
            continue
        titles.append(t)
    return titles


def singular(word):
    if re.search(r'(ch|sh|x)es$', word):
        return word[:-2]
    if len(word) > 3 and word.endswith('s') and not word.endswith('ss'):
        return word[:-1]
    return word


def canonical_key(text):
    text = unicodedata.normalize('NFKD', clean(text)).lower().replace('&', ' and ')
    return ' '.join(singular(w) for w in re.findall(r'[a-z0-9]+', text))


def canonical_name(title, canon):
    key = canonical_key(title)
    best = None
    for name in canon:
        ck = canonical_key(name)
        if key == ck or key.startswith(ck + ' '):
            if best is None or len(ck) > len(canonical_key(best)):
                best = name
    if best:
        return best
    # Fallback: every word of the index title appears in this version's title.
    words = set(key.split())
    for name in canon:
        ck = set(canonical_key(name).split())
        if len(ck) >= 3 and ck <= words:
            if best is None or len(ck) > len(set(canonical_key(best).split())):
                best = name
    return best


def range_for(calories):
    if calories < 200:
        return 0
    return min(calories // 100 * 100, 800)


def main(pdf_dir, out_dir):
    out_dir.mkdir(parents=True, exist_ok=True)
    (out_dir / 'images').mkdir(exist_ok=True)
    scratch = out_dir / '.scratch'
    scratch.mkdir(exist_ok=True)
    recipes = {}
    all_canon = []

    for filename, meal_type in FILES.items():
        path = pdf_dir / filename
        if not path.exists():
            continue
        reader = pypdf.PdfReader(str(path))
        pdf = pdfium.PdfDocument(str(path))
        current_range = None
        last = None
        canon = []
        for p in reader.pages:
            t = p.extract_text() or ''
            if 'Vegan Recipe' in t:
                canon += index_titles(t)
        all_canon += canon
        for index, page in enumerate(reader.pages):
            plain = page.extract_text() or ''
            stripped = plain.strip()
            rm = re.match(r'^(\d{3})(?:-\d{3}|\+)\s*\n?\s*Calories', stripped)
            if rm:
                current_range = int(rm.group(1))
                last = None
                continue
            lines = page_lines(page)
            if 'Fiber' not in plain or 'Sodium' not in plain:
                # Continuation page: more directions or notes for the previous recipe.
                if last and len(stripped) >= 60 and 'Vegan Recipe' not in stripped:
                    for line in [l for l in lines if 40 < l['y'] < 740 and l['x'] >= 240]:
                        if line['x'] >= 252:
                            if last['steps'] and not re.search(r'[.!?)]$', last['steps'][-1]):
                                last['steps'][-1] += ' ' + line['text']
                            else:
                                last['steps'].append(line['text'])
                        else:
                            last['notes_list'].append(line['text'])
                    # Merge wrapped note lines that were split by the page break.
                    merged = []
                    for n in last['notes_list']:
                        if merged and not re.search(r'[.!?"]$', merged[-1]):
                            merged[-1] += ' ' + n
                        else:
                            merged.append(n)
                    last['notes_list'] = merged
                else:
                    last = None
                continue

            parsed = parse_recipe_page(lines, plain)
            if not parsed:
                warnings.append(f'{filename} p{index}: could not parse nutrition')
                continue
            ocr_rows = ocr_page(pdf, index, scratch)
            title = mfp_title(parsed['notes'])
            if not title:
                top = [r for r in sorted(ocr_rows, key=lambda r: -r[1]) if r[1] > 0.88 and 'SERVING' not in r[3].upper()]
                title = clean(' '.join(r[3] for r in top)) if top else None
            if not title:
                warnings.append(f'{filename} p{index}: no title')
                continue

            ingredients = []
            for ing in parsed['ingredients']:
                raw_qty = quantity_for(ing['first'], ocr_rows) if ing['indented'] else None
                quantity, unit = parse_quantity(raw_qty) if raw_qty else (None, None)
                name, note = split_name_note(ing['text'])
                if raw_qty and quantity is None:
                    # Non-numeric amount like "pinch" -> keep it as the unit.
                    unit = raw_qty
                ingredients.append({'name': name, 'quantity': quantity, 'unit': unit, 'note': note})

            calorie_range = current_range if current_range is not None else range_for(parsed['calories'])
            if current_range is not None and range_for(parsed['calories']) not in (current_range,):
                warnings.append(f'{filename} p{index} {title}: {parsed["calories"]} kcal in {current_range} section')

            version = {
                'calorie_range': calorie_range,
                'calories': parsed['calories'],
                'protein_g': parsed['protein_g'],
                'carbs_g': parsed['carbs_g'],
                'fat_g': parsed['fat_g'],
                'fiber_g': parsed['fiber_g'],
                'sugar_g': None,
                'sodium_mg': parsed['sodium_mg'],
                'prep_minutes': parse_minutes(parsed['header']),
                'ingredients': ingredients,
                'steps': parsed['directions'],
                'notes_list': parsed['notes'],
                '_source': f'{filename} p{index + 1}',
            }

            base = canonical_name(title, canon) or canonical_name(title, all_canon)
            if base is None:
                warnings.append(f'{filename} p{index}: "{title}" not in index, kept as its own recipe')
                base = title
            if base != title:
                version['notes_list'].insert(0, f'This version: {title}.')
            slug = slugify(base)
            recipe = recipes.setdefault(slug, {'name': base, 'slug': slug, 'meal_types': [], 'image': None, 'versions': {}})
            if meal_type not in recipe['meal_types']:
                recipe['meal_types'].append(meal_type)
            if calorie_range in recipe['versions']:
                warnings.append(f'duplicate {title} @ {calorie_range}: {recipe["versions"][calorie_range]["_source"]} vs {version["_source"]} (kept first)')
                last = None
                continue
            recipe['versions'][calorie_range] = version
            if recipe['image'] is None:
                photo = extract_photo(page)
                if photo:
                    (out_dir / 'images' / f'{slug}.jpg').write_bytes(photo)
                    recipe['image'] = f'images/{slug}.jpg'
            last = {'steps': version['steps'], 'notes_list': version['notes_list']}
        print(f'{filename}: done', file=sys.stderr)

    output = []
    for recipe in sorted(recipes.values(), key=lambda r: r['name'].lower()):
        versions = []
        for _, v in sorted(recipe['versions'].items()):
            v['notes'] = re.sub(r'(\d)- (\d)', r'\1-\2', '\n\n'.join(v.pop('notes_list'))) or None
            v.pop('_source')
            versions.append(v)
        recipe['versions'] = versions
        output.append(recipe)

    (out_dir / 'recipes.json').write_text(json.dumps(output, indent=2, ensure_ascii=False) + '\n')
    scratch.rmdir()
    print(f'{len(output)} recipes, {sum(len(r["versions"]) for r in output)} versions', file=sys.stderr)
    for w in warnings:
        print('WARN', w, file=sys.stderr)


if __name__ == '__main__':
    main(pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2]))
