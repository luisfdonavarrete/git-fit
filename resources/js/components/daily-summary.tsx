import { Link } from '@inertiajs/react';
import {
    Beef,
    Calculator,
    CalendarClock,
    Flame,
    PencilLine,
    Sparkles,
    Target,
} from 'lucide-react';
import { edit as coachEdit } from '@/actions/App/Http/Controllers/CoachProfileController';
import CaloriesOverrideDialog from '@/components/calories-override-dialog';
import { PhaseBadge } from '@/components/coach-targets-card';
import MacroBar from '@/components/macro-bar';
import NutritionGoalDialog from '@/components/nutrition-goal-dialog';
import { Button } from '@/components/ui/button';
import { dayLabel, formatGrams } from '@/lib/nutrition';
import { cn } from '@/lib/utils';
import type {
    DailyCheckIn,
    DashboardCoach,
    NutritionGoal,
    NutritionSummary,
} from '@/types';

/**
 * The day at a glance: calories remaining (goal − food) as a ring, protein progress, and macro split.
 * With the coach set up it also shows the acceptable calorie range and the coach's phase.
 */
export default function DailySummary({
    summary,
    goal,
    coach,
    checkIn,
    date,
    today,
}: {
    summary: NutritionSummary;
    goal: NutritionGoal | null;
    coach: DashboardCoach | null;
    checkIn: DailyCheckIn;
    date: string;
    today: string;
}) {
    const { totals, targets } = summary;
    const calorieTarget = targets.calories;
    const proteinTarget = targets.protein_g;
    const override = checkIn.calories_override;
    const eaten = override ?? totals.calories;
    const range = coach?.day_calorie_range ?? null;

    return (
        <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            <div className="flex items-center justify-between gap-4 border-b px-5 py-3">
                <h2 className="flex items-center gap-2 text-sm font-medium">
                    Daily summary
                    {coach && <PhaseBadge phase={coach.phase} />}
                </h2>
                {coach ? (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={coachEdit()}>
                            <Sparkles />
                            Coach
                        </Link>
                    </Button>
                ) : (
                    <NutritionGoalDialog
                        goal={goal}
                        trigger={
                            <Button variant="ghost" size="sm">
                                <Target />
                                {goal ? 'Edit goal' : 'Set goal'}
                            </Button>
                        }
                    />
                )}
            </div>

            <div className="grid gap-6 p-5 md:grid-cols-[auto_1fr] md:items-center md:gap-10">
                <CalorieRing
                    eaten={eaten}
                    target={calorieTarget}
                    range={range}
                />

                <div className="space-y-6">
                    {calorieTarget !== null && (
                        <div className="space-y-2">
                            <dl className="grid grid-cols-3 text-center sm:text-left">
                                <Stat label="Goal" value={calorieTarget} />
                                <Stat
                                    label={
                                        override === null
                                            ? 'Food'
                                            : 'Food (typed)'
                                    }
                                    value={eaten}
                                    operator="−"
                                />
                                <Stat
                                    label="Remaining"
                                    value={calorieTarget - eaten}
                                    operator="="
                                    emphasis
                                />
                            </dl>
                            <CaloriesOverrideDialog
                                date={date}
                                diaryCalories={totals.calories}
                                override={override}
                                trigger={
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        <PencilLine className="size-3" />
                                        {override === null
                                            ? "Ate something not in your diary? Log the day's total"
                                            : `Using your typed total · diary says ${totals.calories.toLocaleString()} kcal`}
                                    </button>
                                }
                            />
                        </div>
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

                    {coach && !coach.is_adaptive && (
                        <p className="flex items-start gap-2 rounded-xl bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                            <CalendarClock className="mt-px size-3.5 shrink-0" />
                            Your targets start adapting to your weigh-ins and
                            diary on {dayLabel(coach.adapts_from, today)}. Until
                            then, stick with these numbers.
                        </p>
                    )}

                    {coach && (
                        <Link
                            href={`${coachEdit.url()}#formulas`}
                            className="inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            <Calculator className="size-3" />
                            How are these targets calculated?
                        </Link>
                    )}

                    {calorieTarget === null && (
                        <Link
                            href={coachEdit()}
                            className="flex w-full items-center gap-3 rounded-xl border border-dashed p-3 text-left text-sm transition-colors hover:bg-accent"
                        >
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Sparkles className="size-4" />
                            </span>
                            <span>
                                <span className="block font-medium">
                                    Set up your coach
                                </span>
                                <span className="text-muted-foreground">
                                    Get calorie and macro targets that adjust to
                                    your progress every week.
                                </span>
                            </span>
                        </Link>
                    )}
                </div>
            </div>
        </section>
    );
}

function CalorieRing({
    eaten,
    target,
    range,
}: {
    eaten: number;
    target: number | null;
    range: [number, number] | null;
}) {
    const radius = 54;
    const circumference = 2 * Math.PI * radius;
    const progress = target ? Math.min(eaten / target, 1) : 0;
    // With a coach range, only going past its upper end counts as over.
    const over = target !== null && eaten > (range ? range[1] : target);
    const remaining = target !== null ? target - eaten : null;

    return (
        <div className="mx-auto flex shrink-0 flex-col items-center gap-2">
            <div className="relative size-40">
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
            {range && (
                <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground tabular-nums">
                    Eat within{' '}
                    <span className="font-medium text-foreground">
                        {range[0].toLocaleString()}–{range[1].toLocaleString()}
                    </span>
                </span>
            )}
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
