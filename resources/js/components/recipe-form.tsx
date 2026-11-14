import { Form, Link } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { mealTypeIcons } from '@/lib/meal-type';
import type { MealTypeOption, Recipe } from '@/types';

function slugify(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function RecipeForm({
    form,
    recipe,
    mealTypes,
    submitLabel,
    cancelHref,
}: {
    form: { action: string; method: 'get' | 'post' };
    recipe?: Recipe;
    mealTypes: MealTypeOption[];
    submitLabel: string;
    cancelHref: string;
}) {
    const [name, setName] = useState(recipe?.name ?? '');
    const [slug, setSlug] = useState(recipe?.slug ?? '');
    const [imageUrl, setImageUrl] = useState(recipe?.image_path ?? '');
    const [imageFailed, setImageFailed] = useState(false);

    // Stored photos are paths on the public disk; preview them via the server-built URL.
    const previewUrl = /^https?:\/\//.test(imageUrl)
        ? imageUrl
        : imageUrl !== '' && imageUrl === recipe?.image_path
          ? recipe.image_url
          : null;

    const slugPreview = slug ? slugify(slug) : slugify(name);
    const selectedMealTypes = new Set(
        recipe?.meal_types?.map((m) => m.meal_type) ?? [],
    );

    return (
        <Form {...form} className="grid gap-8 lg:grid-cols-[1fr_18rem]">
            {({ processing, errors }) => (
                <>
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="e.g. Lemon herb chicken bowl"
                                required
                                autoFocus={!recipe}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">
                                Slug{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <div className="flex rounded-md shadow-xs">
                                <span className="inline-flex items-center rounded-l-md border border-r-0 border-input bg-muted px-3 text-sm text-muted-foreground">
                                    /recipes/
                                </span>
                                <Input
                                    id="slug"
                                    name="slug"
                                    value={slug}
                                    onChange={(e) => setSlug(e.target.value)}
                                    placeholder={slugify(name) || 'auto'}
                                    className="rounded-l-none font-mono shadow-none"
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {slugPreview ? (
                                    <>
                                        Saved as{' '}
                                        <span className="font-mono text-foreground">
                                            {slugPreview}
                                        </span>
                                    </>
                                ) : (
                                    'Generated from the name if left blank.'
                                )}
                            </p>
                            <InputError message={errors.slug} />
                        </div>

                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm leading-none font-medium">
                                Meal types{' '}
                                <span className="font-normal text-muted-foreground">
                                    (pick all that apply)
                                </span>
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {mealTypes.map((type) => {
                                    const Icon = mealTypeIcons[type.value];

                                    return (
                                        <label
                                            key={type.value}
                                            className="relative cursor-pointer"
                                        >
                                            <input
                                                type="checkbox"
                                                name="meal_types[]"
                                                value={type.value}
                                                defaultChecked={selectedMealTypes.has(
                                                    type.value,
                                                )}
                                                className="peer sr-only"
                                            />
                                            <span className="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm text-muted-foreground shadow-xs transition-colors peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 hover:bg-accent peer-checked:hover:bg-primary/90">
                                                <Icon className="size-3.5" />
                                                {type.label}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            <InputError
                                message={
                                    errors.meal_types ??
                                    Object.entries(errors).find(([key]) =>
                                        key.startsWith('meal_types.'),
                                    )?.[1]
                                }
                            />
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                name="description"
                                defaultValue={recipe?.description ?? ''}
                                placeholder="A short summary shown on the recipe card"
                                rows={4}
                                className="min-h-28"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="image_path">Image</Label>
                            <Input
                                id="image_path"
                                name="image_path"
                                value={imageUrl}
                                onChange={(e) => {
                                    setImageUrl(e.target.value);
                                    setImageFailed(false);
                                }}
                                placeholder="https://…"
                            />
                            <p className="text-xs text-muted-foreground">
                                An image URL, or a path to a stored photo (e.g.
                                recipes/my-recipe.jpg).
                            </p>
                            <InputError message={errors.image_path} />
                        </div>

                        <div className="flex items-center gap-3 pt-2">
                            <Button type="submit" disabled={processing}>
                                {submitLabel}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={cancelHref}>Cancel</Link>
                            </Button>
                        </div>
                    </div>

                    <aside className="space-y-2">
                        <p className="text-sm font-medium">Preview</p>
                        <div className="overflow-hidden rounded-xl border bg-card">
                            <div className="flex aspect-[4/3] items-center justify-center bg-muted">
                                {previewUrl && !imageFailed ? (
                                    <img
                                        src={previewUrl}
                                        alt=""
                                        className="size-full object-cover"
                                        onError={() => setImageFailed(true)}
                                    />
                                ) : (
                                    <ImageIcon className="size-8 text-muted-foreground/50" />
                                )}
                            </div>
                            <div className="space-y-1 p-4">
                                <p className="truncate font-medium">
                                    {name || 'Untitled recipe'}
                                </p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    {slugPreview || 'slug'}
                                </p>
                            </div>
                        </div>
                        {imageFailed && (
                            <p className="text-xs text-amber-600 dark:text-amber-400">
                                That image couldn't be loaded.
                            </p>
                        )}
                    </aside>
                </>
            )}
        </Form>
    );
}
