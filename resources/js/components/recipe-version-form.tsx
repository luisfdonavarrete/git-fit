import { Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Check,
    ListOrdered,
    Plus,
    ShoppingBasket,
    StickyNote,
    Trash2,
    Flame,
    Gauge,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import MacroBar from '@/components/macro-bar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { caloriesFitRange, rangeLabel, rangeTone } from '@/lib/calorie-range';
import { cn } from '@/lib/utils';
import type {
    CalorieRangeOption,
    CalorieRangeValue,
    RecipeVersion,
} from '@/types';

type IngredientRow = {
    key: string;
    name: string;
    quantity: string;
    unit: string;
    note: string;
};

type StepRow = {
    key: string;
    instruction: string;
};

type VersionFormData = {
    calorie_range: CalorieRangeValue | null;
    calories: string;
    protein_g: string;
    carbs_g: string;
    fat_g: string;
    fiber_g: string;
    sugar_g: string;
    sodium_mg: string;
    notes: string;
    ingredients: IngredientRow[];
    steps: StepRow[];
};

const newKey = () => Math.random().toString(36).slice(2, 10);

const blankIngredient = (): IngredientRow => ({
    key: newKey(),
    name: '',
    quantity: '',
    unit: '',
    note: '',
});

const blankStep = (): StepRow => ({ key: newKey(), instruction: '' });

function move<T>(items: T[], from: number, to: number): T[] {
    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}

function Section({
    icon: Icon,
    title,
    description,
    action,
    children,
}: {
    icon: typeof Flame;
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-card shadow-xs">
            <header className="flex items-start justify-between gap-4 border-b px-5 py-4">
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <Icon className="size-4" />
                    </span>
                    <div>
                        <h2 className="font-medium">{title}</h2>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {action}
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

export default function RecipeVersionForm({
    action,
    method,
    ranges,
    version,
    initialRange,
    cancelHref,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'put';
    ranges: CalorieRangeOption[];
    version?: RecipeVersion;
    initialRange?: CalorieRangeValue | null;
    cancelHref: string;
    submitLabel: string;
}) {
    const form = useForm<VersionFormData>({
        calorie_range: version?.calorie_range ?? initialRange ?? null,
        calories: version ? String(version.calories) : '',
        protein_g: version?.protein_g ?? '',
        carbs_g: version?.carbs_g ?? '',
        fat_g: version?.fat_g ?? '',
        fiber_g: version?.fiber_g ?? '',
        sugar_g: version?.sugar_g ?? '',
        sodium_mg: version?.sodium_mg != null ? String(version.sodium_mg) : '',
        notes: version?.notes ?? '',
        ingredients: version?.ingredients?.length
            ? version.ingredients.map((i) => ({
                  key: newKey(),
                  name: i.name,
                  quantity: i.quantity ?? '',
                  unit: i.unit ?? '',
                  note: i.note ?? '',
              }))
            : [blankIngredient()],
        steps: version?.steps?.length
            ? version.steps.map((s) => ({
                  key: newKey(),
                  instruction: s.instruction,
              }))
            : [blankStep()],
    });

    const { data, setData, processing } = form;
    const errors = form.errors as Record<string, string | undefined>;

    const calories = Number(data.calories);
    const showFitWarning =
        data.calorie_range !== null &&
        data.calories !== '' &&
        !caloriesFitRange(calories, data.calorie_range);

    const updateIngredient = (
        index: number,
        field: keyof Omit<IngredientRow, 'key'>,
        value: string,
    ) =>
        setData(
            'ingredients',
            data.ingredients.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );

    const updateStep = (index: number, value: string) =>
        setData(
            'steps',
            data.steps.map((row, i) =>
                i === index ? { ...row, instruction: value } : row,
            ),
        );

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((current) => ({
            ...current,
            ingredients: current.ingredients
                .filter((row) => row.name.trim() !== '')
                .map(({ key: _key, ...row }) => row),
            steps: current.steps
                .filter((row) => row.instruction.trim() !== '')
                .map(({ key: _key, ...row }) => row),
        }));

        form.submit(method, action, { preserveScroll: true });
    }

    return (
        <form
            onSubmit={submit}
            className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]"
        >
            <div className="space-y-6">
                <Section
                    icon={Gauge}
                    title="Calorie range"
                    description="Which version of the recipe is this?"
                >
                    <div
                        role="radiogroup"
                        aria-label="Calorie range"
                        className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7"
                    >
                        {ranges.map((range) => {
                            const selected = data.calorie_range === range.value;
                            const disabled = range.available === false;

                            return (
                                <button
                                    key={range.value}
                                    type="button"
                                    role="radio"
                                    aria-checked={selected}
                                    disabled={disabled}
                                    onClick={() =>
                                        setData('calorie_range', range.value)
                                    }
                                    className={cn(
                                        'relative flex flex-col items-start gap-2 rounded-lg border px-3 py-2.5 text-left transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        selected
                                            ? cn(
                                                  'bg-accent ring-2',
                                                  rangeTone(range.value).ring,
                                              )
                                            : 'hover:bg-accent/60',
                                        disabled &&
                                            'cursor-not-allowed opacity-40 hover:bg-transparent',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'h-1 w-6 rounded-full',
                                            rangeTone(range.value).solid,
                                        )}
                                    />
                                    <span className="text-sm font-semibold tabular-nums">
                                        {rangeLabel(range.value)}
                                    </span>
                                    <span className="text-[11px] text-muted-foreground">
                                        {disabled ? 'Already added' : 'kcal'}
                                    </span>
                                    {selected && (
                                        <Check className="absolute top-2 right-2 size-3.5" />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    <InputError
                        className="mt-3"
                        message={errors.calorie_range}
                    />
                </Section>

                <Section
                    icon={Flame}
                    title="Nutrition"
                    description="Per serving. Leave anything you don't track blank."
                >
                    <div className="grid gap-5">
                        <div className="grid gap-2 sm:max-w-56">
                            <Label htmlFor="calories">Calories</Label>
                            <div className="relative">
                                <Input
                                    id="calories"
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    value={data.calories}
                                    onChange={(e) =>
                                        setData('calories', e.target.value)
                                    }
                                    required
                                    className="h-11 pr-14 text-lg font-semibold tabular-nums"
                                />
                                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                    kcal
                                </span>
                            </div>
                            {showFitWarning && data.calorie_range && (
                                <p className="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                                    <AlertTriangle className="size-3.5" />
                                    Outside the {rangeLabel(
                                        data.calorie_range,
                                    )}{' '}
                                    range
                                </p>
                            )}
                            <InputError message={errors.calories} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    ['protein_g', 'Protein', 'g'],
                                    ['carbs_g', 'Carbs', 'g'],
                                    ['fat_g', 'Fat', 'g'],
                                    ['fiber_g', 'Fiber', 'g'],
                                    ['sugar_g', 'Sugar', 'g'],
                                    ['sodium_mg', 'Sodium', 'mg'],
                                ] as const
                            ).map(([field, label, unit]) => (
                                <div key={field} className="grid gap-2">
                                    <Label htmlFor={field}>{label}</Label>
                                    <div className="relative">
                                        <Input
                                            id={field}
                                            type="number"
                                            inputMode="decimal"
                                            min={0}
                                            step={unit === 'mg' ? 1 : 0.1}
                                            value={data[field]}
                                            onChange={(e) =>
                                                setData(field, e.target.value)
                                            }
                                            className="pr-10 tabular-nums"
                                        />
                                        <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
                                            {unit}
                                        </span>
                                    </div>
                                    <InputError message={errors[field]} />
                                </div>
                            ))}
                        </div>
                    </div>
                </Section>

                <Section
                    icon={ShoppingBasket}
                    title="Ingredients"
                    description="Empty rows are ignored when saving."
                    action={
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums">
                            {
                                data.ingredients.filter((i) => i.name.trim())
                                    .length
                            }
                        </span>
                    }
                >
                    <div className="space-y-2">
                        <div className="hidden grid-cols-[5rem_5rem_minmax(0,1fr)_minmax(0,0.8fr)_auto] gap-2 px-1 text-xs font-medium text-muted-foreground md:grid">
                            <span>Qty</span>
                            <span>Unit</span>
                            <span>Ingredient</span>
                            <span>Note</span>
                            <span className="w-[5.25rem]" />
                        </div>
                        {data.ingredients.map((row, index) => (
                            <div key={row.key} className="space-y-1">
                                <div className="grid grid-cols-[4.5rem_4.5rem_minmax(0,1fr)] gap-2 md:grid-cols-[5rem_5rem_minmax(0,1fr)_minmax(0,0.8fr)_auto]">
                                    <Input
                                        aria-label="Quantity"
                                        type="number"
                                        inputMode="decimal"
                                        min={0}
                                        step="any"
                                        value={row.quantity}
                                        onChange={(e) =>
                                            updateIngredient(
                                                index,
                                                'quantity',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="1"
                                        className="tabular-nums"
                                    />
                                    <Input
                                        aria-label="Unit"
                                        value={row.unit}
                                        onChange={(e) =>
                                            updateIngredient(
                                                index,
                                                'unit',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="cup"
                                    />
                                    <Input
                                        aria-label="Ingredient"
                                        value={row.name}
                                        onChange={(e) =>
                                            updateIngredient(
                                                index,
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ingredient"
                                        aria-invalid={
                                            !!errors[
                                                `ingredients.${index}.name`
                                            ]
                                        }
                                    />
                                    <Input
                                        aria-label="Note"
                                        value={row.note}
                                        onChange={(e) =>
                                            updateIngredient(
                                                index,
                                                'note',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="finely chopped"
                                        className="col-span-2 md:col-span-1"
                                    />
                                    <RowControls
                                        index={index}
                                        count={data.ingredients.length}
                                        onMove={(to) =>
                                            setData(
                                                'ingredients',
                                                move(
                                                    data.ingredients,
                                                    index,
                                                    to,
                                                ),
                                            )
                                        }
                                        onRemove={() =>
                                            setData(
                                                'ingredients',
                                                data.ingredients.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    />
                                </div>
                                <InputError
                                    message={
                                        errors[`ingredients.${index}.name`] ??
                                        errors[`ingredients.${index}.quantity`]
                                    }
                                />
                            </div>
                        ))}
                        <AddRowButton
                            onClick={() =>
                                setData('ingredients', [
                                    ...data.ingredients,
                                    blankIngredient(),
                                ])
                            }
                        >
                            Add ingredient
                        </AddRowButton>
                    </div>
                </Section>

                <Section
                    icon={ListOrdered}
                    title="Directions"
                    description="One step per box, in the order they're done."
                    action={
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums">
                            {
                                data.steps.filter((s) => s.instruction.trim())
                                    .length
                            }
                        </span>
                    }
                >
                    <ol className="space-y-3">
                        {data.steps.map((row, index) => (
                            <li key={row.key} className="flex gap-3">
                                <span className="mt-1.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <Textarea
                                        aria-label={`Step ${index + 1}`}
                                        value={row.instruction}
                                        onChange={(e) =>
                                            updateStep(index, e.target.value)
                                        }
                                        placeholder="Describe this step…"
                                        rows={2}
                                        aria-invalid={
                                            !!errors[
                                                `steps.${index}.instruction`
                                            ]
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[`steps.${index}.instruction`]
                                        }
                                    />
                                </div>
                                <RowControls
                                    vertical
                                    index={index}
                                    count={data.steps.length}
                                    onMove={(to) =>
                                        setData(
                                            'steps',
                                            move(data.steps, index, to),
                                        )
                                    }
                                    onRemove={() =>
                                        setData(
                                            'steps',
                                            data.steps.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                />
                            </li>
                        ))}
                    </ol>
                    <AddRowButton
                        className="mt-3"
                        onClick={() =>
                            setData('steps', [...data.steps, blankStep()])
                        }
                    >
                        Add step
                    </AddRowButton>
                </Section>

                <Section
                    icon={StickyNote}
                    title="Notes"
                    description="Optional tips, swaps, or storage advice for this version."
                >
                    <Textarea
                        aria-label="Notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="e.g. Swap the rice for cauliflower rice to drop another 100 kcal."
                        rows={3}
                        className="min-h-24"
                    />
                    <InputError className="mt-2" message={errors.notes} />
                </Section>
            </div>

            <aside className="space-y-4 xl:sticky xl:top-4">
                <div className="rounded-xl border bg-card p-5 shadow-xs">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Summary
                    </p>
                    <div className="mt-3 flex items-baseline gap-1.5">
                        <span className="text-4xl font-semibold tracking-tight tabular-nums">
                            {data.calories || '—'}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            kcal
                        </span>
                    </div>
                    {data.calorie_range && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {rangeLabel(data.calorie_range)} kcal version
                        </p>
                    )}
                    <MacroBar
                        className="mt-5"
                        protein={Number(data.protein_g) || 0}
                        carbs={Number(data.carbs_g) || 0}
                        fat={Number(data.fat_g) || 0}
                    />
                    <dl className="mt-5 grid grid-cols-2 gap-3 border-t pt-4 text-sm">
                        <div>
                            <dt className="text-muted-foreground">
                                Ingredients
                            </dt>
                            <dd className="font-medium tabular-nums">
                                {
                                    data.ingredients.filter((i) =>
                                        i.name.trim(),
                                    ).length
                                }
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Steps</dt>
                            <dd className="font-medium tabular-nums">
                                {
                                    data.steps.filter((s) =>
                                        s.instruction.trim(),
                                    ).length
                                }
                            </dd>
                        </div>
                    </dl>
                </div>

                <div className="flex gap-2">
                    <Button
                        type="submit"
                        className="flex-1"
                        disabled={processing}
                    >
                        {submitLabel}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={cancelHref}>Cancel</Link>
                    </Button>
                </div>
                {Object.keys(errors).length > 0 && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        Please fix the highlighted fields.
                    </p>
                )}
            </aside>
        </form>
    );
}

function RowControls({
    index,
    count,
    onMove,
    onRemove,
    vertical = false,
}: {
    index: number;
    count: number;
    onMove: (to: number) => void;
    onRemove: () => void;
    vertical?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center gap-0.5 text-muted-foreground',
                vertical ? 'flex-col' : 'col-span-1 justify-end md:col-span-1',
            )}
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7"
                aria-label="Move up"
                disabled={index === 0}
                onClick={() => onMove(index - 1)}
            >
                <ArrowUp className="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7"
                aria-label="Move down"
                disabled={index === count - 1}
                onClick={() => onMove(index + 1)}
            >
                <ArrowDown className="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7 hover:text-red-600 dark:hover:text-red-400"
                aria-label="Remove"
                onClick={onRemove}
            >
                <Trash2 className="size-3.5" />
            </Button>
        </div>
    );
}

function AddRowButton({
    onClick,
    children,
    className,
}: {
    onClick: () => void;
    children: ReactNode;
    className?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed py-2.5 text-sm text-muted-foreground transition-colors hover:border-foreground/30 hover:bg-accent/50 hover:text-foreground',
                className,
            )}
        >
            <Plus className="size-4" />
            {children}
        </button>
    );
}
