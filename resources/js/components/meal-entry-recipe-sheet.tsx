import { useHttp } from '@inertiajs/react';
import {
    Clock,
    Lightbulb,
    ListOrdered,
    ShoppingBasket,
    UtensilsCrossed,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/MealEntryController';
import CalorieRangeBadge from '@/components/calorie-range-badge';
import MacroBar from '@/components/macro-bar';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { formatServings, grams } from '@/lib/nutrition';
import type { DiaryEntry, MealEntryRecipe } from '@/types';

/**
 * A read-only look at the recipe behind a diary entry: its ingredients (scaled to the entry's
 * servings), directions and notes. The recipe loads each time the sheet opens, so a swapped
 * plan entry always shows its current recipe.
 */
export default function MealEntryRecipeSheet({
    entry,
    open,
    onOpenChange,
}: {
    entry: DiaryEntry;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [recipe, setRecipe] = useState<MealEntryRecipe | null | undefined>(
        undefined,
    );
    const [failed, setFailed] = useState(false);
    const http = useHttp<
        Record<string, never>,
        { recipe: MealEntryRecipe | null }
    >();
    const servings = Number(entry.servings);

    useEffect(() => {
        if (!open) {
            return;
        }

        let current = true;
        setRecipe(undefined);
        setFailed(false);
        http.get(show.url(entry.id))
            .then((response) => current && setRecipe(response.recipe))
            .catch(() => current && setFailed(true));

        return () => {
            current = false;
        };
    }, [open, entry.id]);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                <div className="aspect-[16/9] w-full shrink-0 overflow-hidden bg-muted">
                    {entry.image_url ? (
                        <img
                            src={entry.image_url}
                            alt=""
                            className="size-full object-cover"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center">
                            <UtensilsCrossed className="size-8 text-muted-foreground/40" />
                        </div>
                    )}
                </div>

                <SheetHeader className="gap-2 p-5 pb-4">
                    <SheetTitle className="pr-6 text-lg leading-snug">
                        {entry.name}
                    </SheetTitle>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground tabular-nums">
                        <CalorieRangeBadge
                            value={entry.calorie_range}
                            className="px-1.5 py-0 text-[11px]"
                        />
                        <span>{formatServings(servings)}</span>
                        <span>·</span>
                        <span className="font-medium text-foreground">
                            {entry.calories} kcal
                        </span>
                        {recipe?.prep_minutes != null && (
                            <span className="flex items-center gap-1">
                                <Clock className="size-3" />
                                {formatMinutes(recipe.prep_minutes)}
                            </span>
                        )}
                    </div>
                    <SheetDescription
                        className={recipe?.description ? '' : 'sr-only'}
                    >
                        {recipe?.description ??
                            'Ingredients and directions for this meal.'}
                    </SheetDescription>
                    <MacroBar
                        className="mt-2"
                        protein={grams(entry.protein_g)}
                        carbs={grams(entry.carbs_g)}
                        fat={grams(entry.fat_g)}
                    />
                </SheetHeader>

                <div className="flex flex-col gap-6 border-t p-5">
                    {failed ? (
                        <p className="text-sm text-muted-foreground">
                            Couldn’t load this recipe. Try again in a moment.
                        </p>
                    ) : recipe === undefined ? (
                        <RecipeSkeleton />
                    ) : recipe === null ? (
                        <p className="text-sm text-muted-foreground">
                            This recipe version is no longer in the library, so
                            its ingredients and directions aren’t available.
                        </p>
                    ) : (
                        <RecipeDetails recipe={recipe} servings={servings} />
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function RecipeDetails({
    recipe,
    servings,
}: {
    recipe: MealEntryRecipe;
    servings: number;
}) {
    return (
        <>
            <section>
                <h3 className="flex items-center gap-2 text-sm font-medium">
                    <ShoppingBasket className="size-4 text-muted-foreground" />
                    Ingredients
                    {servings !== 1 && (
                        <span className="ml-auto text-xs font-normal text-muted-foreground">
                            for {formatServings(servings)}
                        </span>
                    )}
                </h3>
                {recipe.ingredients.length ? (
                    <ul className="mt-2 divide-y text-sm">
                        {recipe.ingredients.map((ingredient) => (
                            <li
                                key={ingredient.id}
                                className="flex items-baseline gap-3 py-2"
                            >
                                <span className="w-16 shrink-0 text-right font-medium tabular-nums">
                                    {scaleQuantity(
                                        ingredient.quantity,
                                        servings,
                                    )}{' '}
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
                    <p className="mt-2 text-sm text-muted-foreground">
                        No ingredients listed.
                    </p>
                )}
            </section>

            <section>
                <h3 className="flex items-center gap-2 text-sm font-medium">
                    <ListOrdered className="size-4 text-muted-foreground" />
                    Directions
                </h3>
                {recipe.steps.length ? (
                    <ol className="mt-3 space-y-4">
                        {recipe.steps.map((step, index) => (
                            <li key={step.id} className="flex gap-3">
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <p className="text-sm leading-relaxed whitespace-pre-line">
                                    {step.instruction}
                                </p>
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="mt-2 text-sm text-muted-foreground">
                        No directions yet.
                    </p>
                )}
            </section>

            {recipe.notes && (
                <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                    <Lightbulb className="mt-0.5 size-4 shrink-0 text-amber-500" />
                    <p className="whitespace-pre-line">{recipe.notes}</p>
                </div>
            )}
        </>
    );
}

function RecipeSkeleton() {
    return (
        <div className="space-y-3" aria-busy="true">
            <Skeleton className="h-4 w-28" />
            {Array.from({ length: 4 }, (_, index) => (
                <Skeleton key={index} className="h-4 w-full" />
            ))}
            <Skeleton className="mt-4 h-4 w-24" />
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
        </div>
    );
}

/**
 * A recipe quantity multiplied by the entry's servings, trimmed to at most two decimals.
 */
function scaleQuantity(quantity: string | null, servings: number): string {
    if (!quantity) {
        return '';
    }

    return String(Math.round(Number(quantity) * servings * 100) / 100);
}

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}
