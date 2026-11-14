import { Head, Link, usePage } from '@inertiajs/react';
import {
    Clock,
    ListOrdered,
    Lightbulb,
    Pencil,
    Plus,
    ShoppingBasket,
    Trash2,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/RecipeController';
import RecipeVersionController from '@/actions/App/Http/Controllers/Admin/RecipeVersionController';
import CalorieRangeBadge from '@/components/calorie-range-badge';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import MacroBar from '@/components/macro-bar';
import MealTypeBadge from '@/components/meal-type-badge';
import { Button } from '@/components/ui/button';
import { rangeLabel, rangeTone } from '@/lib/calorie-range';
import { cn } from '@/lib/utils';
import type {
    CalorieRangeOption,
    CalorieRangeValue,
    Recipe,
    RecipeVersion,
} from '@/types';

type Props = {
    recipe: Recipe & { versions: RecipeVersion[] };
    calorieRanges: CalorieRangeOption[];
};

function rangeFromUrl(url: string): number | null {
    const value = new URLSearchParams(url.split('?')[1] ?? '').get('range');

    return value ? Number(value) : null;
}

export default function ShowRecipe({ recipe, calorieRanges }: Props) {
    const { url } = usePage();
    const versions = recipe.versions;
    const requested = rangeFromUrl(url);

    const [selected, setSelected] = useState<CalorieRangeValue | null>(
        () =>
            versions.find((v) => v.calorie_range === requested)
                ?.calorie_range ??
            versions[0]?.calorie_range ??
            null,
    );

    const byRange = new Map(versions.map((v) => [v.calorie_range, v]));
    const current = selected !== null ? byRange.get(selected) : undefined;

    return (
        <>
            <Head title={recipe.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-5 md:flex-row md:items-start">
                    <div className="size-24 shrink-0 overflow-hidden rounded-xl border bg-muted md:size-28">
                        {recipe.image_url ? (
                            <img
                                src={recipe.image_url}
                                alt=""
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center">
                                <UtensilsCrossed className="size-7 text-muted-foreground/40" />
                            </div>
                        )}
                    </div>
                    <div className="min-w-0 flex-1 space-y-1.5">
                        <p className="font-mono text-xs text-muted-foreground">
                            /{recipe.slug}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                            {recipe.name}
                        </h1>
                        {recipe.description && (
                            <p className="max-w-2xl text-sm text-pretty text-muted-foreground">
                                {recipe.description}
                            </p>
                        )}
                        {recipe.meal_types && recipe.meal_types.length > 0 && (
                            <div className="flex flex-wrap gap-1.5 pt-1">
                                {recipe.meal_types.map((m) => (
                                    <MealTypeBadge
                                        key={m.id}
                                        value={m.meal_type}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <Button variant="outline" asChild>
                            <Link href={edit(recipe.id)}>
                                <Pencil />
                                Edit details
                            </Link>
                        </Button>
                        <ConfirmDeleteDialog
                            title={`Delete “${recipe.name}”?`}
                            description={`This permanently removes the recipe and all ${versions.length} of its calorie versions, including their ingredients and directions.`}
                            confirmLabel="Delete recipe"
                            form={destroy.form(recipe.id)}
                            trigger={
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Delete recipe"
                                    className="hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:hover:border-red-500/30 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <Trash2 />
                                </Button>
                            }
                        />
                    </div>
                </header>

                <nav
                    aria-label="Calorie versions"
                    className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8"
                >
                    {calorieRanges.map((range) => {
                        const version = byRange.get(range.value);
                        const isSelected = selected === range.value;

                        if (!version) {
                            return (
                                <Link
                                    key={range.value}
                                    href={RecipeVersionController.create(
                                        recipe.id,
                                        {
                                            query: { range: range.value },
                                        },
                                    )}
                                    className="group flex flex-col justify-between gap-3 rounded-xl border border-dashed p-3 text-muted-foreground transition-colors hover:border-foreground/30 hover:bg-accent/50 hover:text-foreground"
                                >
                                    <span className="text-sm font-medium tabular-nums">
                                        {rangeLabel(range.value)}
                                    </span>
                                    <span className="flex items-center gap-1 text-xs">
                                        <Plus className="size-3.5" />
                                        Add version
                                    </span>
                                </Link>
                            );
                        }

                        return (
                            <button
                                key={range.value}
                                type="button"
                                onClick={() => setSelected(range.value)}
                                aria-pressed={isSelected}
                                className={cn(
                                    'flex flex-col justify-between gap-3 rounded-xl border bg-card p-3 text-left shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    isSelected
                                        ? cn(
                                              'ring-2',
                                              rangeTone(range.value).ring,
                                          )
                                        : 'hover:bg-accent/60',
                                )}
                            >
                                <span className="flex items-center justify-between">
                                    <span className="text-sm font-semibold tabular-nums">
                                        {rangeLabel(range.value)}
                                    </span>
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            rangeTone(range.value).solid,
                                        )}
                                    />
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {version.calories} kcal ·{' '}
                                    {version.ingredients?.length ?? 0} ingr.
                                </span>
                            </button>
                        );
                    })}
                </nav>

                {current ? (
                    <VersionDetail recipe={recipe} version={current} />
                ) : (
                    <NoVersions recipeId={recipe.id} />
                )}
            </div>
        </>
    );
}

function VersionDetail({
    recipe,
    version,
}: {
    recipe: Recipe;
    version: RecipeVersion;
}) {
    const ingredients = version.ingredients ?? [];
    const steps = version.steps ?? [];
    const routeArgs = { recipe: recipe.id, version: version.id };

    const extras = [
        { label: 'Fiber', value: version.fiber_g, unit: 'g' },
        { label: 'Sugar', value: version.sugar_g, unit: 'g' },
        { label: 'Sodium', value: version.sodium_mg, unit: 'mg' },
    ];

    return (
        <div className="grid items-start gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
            <aside className="space-y-4 xl:sticky xl:top-4">
                <div className="rounded-xl border bg-card p-5 shadow-xs">
                    <div className="flex items-center justify-between">
                        <CalorieRangeBadge value={version.calorie_range} />
                        <div className="flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                asChild
                            >
                                <Link
                                    href={RecipeVersionController.edit(
                                        routeArgs,
                                    )}
                                    aria-label="Edit version"
                                >
                                    <Pencil className="size-3.5" />
                                </Link>
                            </Button>
                            <ConfirmDeleteDialog
                                title={`Delete the ${rangeLabel(version.calorie_range)} kcal version?`}
                                description="Its nutrition, ingredients, directions and notes will be permanently removed. Other versions are not affected."
                                confirmLabel="Delete version"
                                form={RecipeVersionController.destroy.form(
                                    routeArgs,
                                )}
                                trigger={
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 hover:text-red-600 dark:hover:text-red-400"
                                        aria-label="Delete version"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                }
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex items-baseline gap-1.5">
                        <span className="text-5xl font-semibold tracking-tight tabular-nums">
                            {version.calories}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            kcal / serving
                        </span>
                    </div>
                    {version.prep_minutes != null && (
                        <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Clock className="size-3.5" />
                            {formatMinutes(version.prep_minutes)}
                        </p>
                    )}

                    <MacroBar
                        className="mt-5"
                        protein={Number(version.protein_g) || 0}
                        carbs={Number(version.carbs_g) || 0}
                        fat={Number(version.fat_g) || 0}
                    />

                    <dl className="mt-5 grid grid-cols-3 gap-2 border-t pt-4 text-xs">
                        {extras.map((item) => (
                            <div key={item.label}>
                                <dt className="text-muted-foreground">
                                    {item.label}
                                </dt>
                                <dd className="mt-0.5 font-medium tabular-nums">
                                    {item.value != null && item.value !== ''
                                        ? `${item.value}${item.unit}`
                                        : '—'}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                {version.notes && (
                    <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                        <Lightbulb className="mt-0.5 size-4 shrink-0 text-amber-500" />
                        <p className="whitespace-pre-line">{version.notes}</p>
                    </div>
                )}
            </aside>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <section className="rounded-xl border bg-card shadow-xs">
                    <header className="flex items-center gap-2 border-b px-5 py-3.5">
                        <ShoppingBasket className="size-4 text-muted-foreground" />
                        <h2 className="font-medium">Ingredients</h2>
                        <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                            {ingredients.length}
                        </span>
                    </header>
                    {ingredients.length ? (
                        <ul className="divide-y">
                            {ingredients.map((ingredient) => (
                                <li
                                    key={ingredient.id}
                                    className="flex items-baseline gap-3 px-5 py-2.5 text-sm"
                                >
                                    <span className="w-20 shrink-0 text-right font-medium tabular-nums">
                                        {ingredient.quantity
                                            ? Number(ingredient.quantity)
                                            : ''}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            {ingredient.unit}
                                        </span>
                                    </span>
                                    <span className="min-w-0">
                                        {ingredient.name}
                                        {ingredient.note && (
                                            <span className="text-muted-foreground">
                                                , {ingredient.note}
                                            </span>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptySection>No ingredients listed.</EmptySection>
                    )}
                </section>

                <section className="rounded-xl border bg-card shadow-xs">
                    <header className="flex items-center gap-2 border-b px-5 py-3.5">
                        <ListOrdered className="size-4 text-muted-foreground" />
                        <h2 className="font-medium">Directions</h2>
                        <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                            {steps.length}{' '}
                            {steps.length === 1 ? 'step' : 'steps'}
                        </span>
                    </header>
                    {steps.length ? (
                        <ol className="space-y-5 p-5">
                            {steps.map((step, i) => (
                                <li key={step.id} className="flex gap-4">
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground tabular-nums">
                                        {i + 1}
                                    </span>
                                    <p className="pt-0.5 text-sm leading-relaxed whitespace-pre-line">
                                        {step.instruction}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <EmptySection>No directions yet.</EmptySection>
                    )}
                </section>
            </div>
        </div>
    );
}

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

function EmptySection({ children }: { children: React.ReactNode }) {
    return (
        <p className="px-5 py-10 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

function NoVersions({ recipeId }: { recipeId: number }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-16 text-center">
            <h2 className="font-medium">No calorie versions yet</h2>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Each version has its own nutrition, ingredients and directions.
                Pick a range above or start with the first one.
            </p>
            <Button className="mt-6" asChild>
                <Link href={RecipeVersionController.create(recipeId)}>
                    <Plus />
                    Add first version
                </Link>
            </Button>
        </div>
    );
}

ShowRecipe.layout = ({ recipe }: Props) => ({
    breadcrumbs: [
        { title: 'Recipes', href: index() },
        { title: recipe.name, href: show(recipe.id) },
    ],
});
