import { Beef, Flame, Target } from 'lucide-react';
import MacroBar from '@/components/macro-bar';
import NutritionGoalDialog from '@/components/nutrition-goal-dialog';
import { Button } from '@/components/ui/button';
import { formatGrams } from '@/lib/nutrition';
import { cn } from '@/lib/utils';
import type { NutritionGoal, NutritionSummary } from '@/types';

/**
 * The day at a glance: calories remaining (goal − food) as a ring, protein progress, and macro split.
 */
export default function DailySummary({
    summary,
    goal,
}: {
    summary: NutritionSummary;
    goal: NutritionGoal | null;
}) {
    const { totals, targets } = summary;
    const calorieTarget = targets.calories;
    const proteinTarget = targets.protein_g;

    return (
        <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            <div className="flex items-center justify-between gap-4 border-b px-5 py-3">
                <h2 className="text-sm font-medium">Daily summary</h2>
                <NutritionGoalDialog
                    goal={goal}
                    trigger={
                        <Button variant="ghost" size="sm">
                            <Target />
                            {goal ? 'Edit goal' : 'Set goal'}
                        </Button>
                    }
                />
            </div>

            <div className="grid gap-6 p-5 md:grid-cols-[auto_1fr] md:items-center md:gap-10">
                <CalorieRing eaten={totals.calories} target={calorieTarget} />

                <div className="space-y-6">
                    {calorieTarget !== null && (
                        <dl className="grid grid-cols-3 text-center sm:text-left">
                            <Stat label="Goal" value={calorieTarget} />
                            <Stat
                                label="Food"
                                value={totals.calories}
                                operator="−"
                            />
                            <Stat
                                label="Remaining"
                                value={calorieTarget - totals.calories}
                                operator="="
                                emphasis
                            />
                        </dl>
                    )}

                    <ProteinProgress
                        eaten={totals.protein_g}
                        target={proteinTarget}
                    />

                    <MacroBar
                        protein={totals.protein_g}
                        carbs={totals.carbs_g}
                        fat={totals.fat_g}
                    />

                    {calorieTarget === null && (
                        <NutritionGoalDialog
                            goal={goal}
                            trigger={
                                <button
                                    type="button"
                                    className="flex w-full items-center gap-3 rounded-xl border border-dashed p-3 text-left text-sm transition-colors hover:bg-accent"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Target className="size-4" />
                                    </span>
                                    <span>
                                        <span className="block font-medium">
                                            Set a daily goal
                                        </span>
                                        <span className="text-muted-foreground">
                                            Track calories and protein against a
                                            target.
                                        </span>
                                    </span>
                                </button>
                            }
                        />
                    )}
                </div>
            </div>
        </section>
    );
}

function CalorieRing({
    eaten,
    target,
}: {
    eaten: number;
    target: number | null;
}) {
    const radius = 54;
    const circumference = 2 * Math.PI * radius;
    const progress = target ? Math.min(eaten / target, 1) : 0;
    const over = target !== null && eaten > target;
    const remaining = target !== null ? target - eaten : null;

    return (
        <div className="relative mx-auto size-40 shrink-0">
            <svg viewBox="0 0 128 128" className="size-full -rotate-90">
                <circle
                    cx="64"
                    cy="64"
                    r={radius}
                    fill="none"
                    strokeWidth="10"
                    className="stroke-muted"
                />
                <circle
                    cx="64"
                    cy="64"
                    r={radius}
                    fill="none"
                    strokeWidth="10"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference * (1 - progress)}
                    className={cn(
                        'transition-[stroke-dashoffset] duration-700 ease-out',
                        over ? 'stroke-rose-500' : 'stroke-emerald-500',
                    )}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                <Flame
                    className={cn(
                        'mb-0.5 size-4',
                        over ? 'text-rose-500' : 'text-emerald-500',
                    )}
                />
                <span className="text-3xl font-semibold tracking-tight tabular-nums">
                    {remaining !== null
                        ? Math.abs(remaining).toLocaleString()
                        : eaten.toLocaleString()}
                </span>
                <span className="text-xs text-muted-foreground">
                    {remaining === null
                        ? 'kcal eaten'
                        : over
                          ? 'kcal over'
                          : 'kcal left'}
                </span>
            </div>
        </div>
    );
}

function Stat({
    label,
    value,
    operator,
    emphasis = false,
}: {
    label: string;
    value: number;
    operator?: string;
    emphasis?: boolean;
}) {
    return (
        <div className="relative">
            {operator && (
                <span
                    aria-hidden
                    className="absolute top-1 -left-1 text-sm text-muted-foreground sm:-left-4"
                >
                    {operator}
                </span>
            )}
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'text-lg font-semibold tabular-nums',
                    emphasis && value < 0 && 'text-rose-600 dark:text-rose-400',
                    emphasis &&
                        value >= 0 &&
                        'text-emerald-600 dark:text-emerald-400',
                )}
            >
                {value.toLocaleString()}
            </dd>
        </div>
    );
}

function ProteinProgress({
    eaten,
    target,
}: {
    eaten: number;
    target: number | null;
}) {
    const percent = target ? Math.min((eaten / target) * 100, 100) : 0;
    const reached = target !== null && eaten >= target;

    return (
        <div className="space-y-2">
            <div className="flex items-baseline justify-between text-sm">
                <span className="flex items-center gap-1.5 font-medium">
                    <Beef className="size-4 text-sky-500" />
                    Protein
                </span>
                <span className="tabular-nums">
                    <span className="font-semibold">{formatGrams(eaten)}</span>
                    {target !== null && (
                        <span className="text-muted-foreground">
                            {' '}
                            / {formatGrams(target)}
                        </span>
                    )}
                </span>
            </div>
            {target !== null && (
                <div className="h-2.5 overflow-hidden rounded-full bg-muted">
                    <div
                        className={cn(
                            'h-full rounded-full transition-[width] duration-700 ease-out',
                            reached ? 'bg-emerald-500' : 'bg-sky-500',
                        )}
                        style={{ width: `${percent}%` }}
                    />
                </div>
            )}
        </div>
    );
}
