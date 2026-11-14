import { Plus } from 'lucide-react';
import AddMealDialog from '@/components/add-meal-dialog';
import MealEntryRow from '@/components/meal-entry-row';
import { Button } from '@/components/ui/button';
import { mealTypeIcons } from '@/lib/meal-type';
import { formatGrams, grams } from '@/lib/nutrition';
import type { DiaryMeal } from '@/types';

const accents: Record<DiaryMeal['type'], string> = {
    breakfast: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    lunch: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    dinner: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    snack: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
};

/**
 * One diary section (Breakfast, Lunch, …): its logged foods, totals, and an "Add food" action.
 */
export default function MealSection({
    meal,
    date,
}: {
    meal: DiaryMeal;
    date: string;
}) {
    const Icon = mealTypeIcons[meal.type];
    const calories = meal.entries.reduce((sum, e) => sum + e.calories, 0);
    const protein = meal.entries.reduce(
        (sum, e) => sum + grams(e.protein_g),
        0,
    );

    return (
        <section className="flex flex-col overflow-hidden rounded-2xl border bg-card shadow-xs">
            <header className="flex items-center gap-3 px-4 py-3">
                <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-xl ${accents[meal.type]}`}
                >
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="font-medium">{meal.label}</h2>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {meal.entries.length === 0
                            ? 'Nothing logged yet'
                            : `${calories.toLocaleString()} kcal · ${formatGrams(Math.round(protein * 10) / 10)} protein`}
                    </p>
                </div>
                <AddMealDialog
                    meal={meal}
                    date={date}
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="rounded-full"
                            aria-label={`Add food to ${meal.label}`}
                        >
                            <Plus />
                        </Button>
                    }
                />
            </header>

            {meal.entries.length > 0 ? (
                <ul className="divide-y border-t">
                    {meal.entries.map((entry) => (
                        <MealEntryRow
                            key={entry.id}
                            entry={entry}
                            mealType={meal.type}
                        />
                    ))}
                </ul>
            ) : (
                <AddMealDialog
                    meal={meal}
                    date={date}
                    trigger={
                        <button
                            type="button"
                            className="mx-4 mb-4 flex items-center justify-center gap-2 rounded-xl border border-dashed py-5 text-sm text-muted-foreground transition-colors hover:border-foreground/20 hover:bg-accent hover:text-foreground"
                        >
                            <Plus className="size-4" />
                            Add food
                        </button>
                    }
                />
            )}
        </section>
    );
}
