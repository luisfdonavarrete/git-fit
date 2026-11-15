import {
    Activity,
    CalendarClock,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { dayLabel } from '@/lib/nutrition';
import { cn } from '@/lib/utils';
import type { CoachTargets } from '@/types';

const phaseTones: Record<CoachTargets['phase']['value'], string> = {
    cut: 'bg-sky-500/10 text-sky-700 ring-sky-500/20 dark:text-sky-300',
    maintain:
        'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-300',
    lean_bulk:
        'bg-amber-500/10 text-amber-700 ring-amber-500/25 dark:text-amber-300',
};

export function PhaseBadge({
    phase,
    className,
}: {
    phase: CoachTargets['phase'];
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                phaseTones[phase.value],
                className,
            )}
        >
            {phase.label}
        </span>
    );
}

/**
 * The coach's current recommendation: calorie range, how it was derived, and macro targets.
 */
export default function CoachTargetsCard({
    targets,
    today,
}: {
    targets: CoachTargets;
    today: string;
}) {
    const Trend = targets.weekly_change < 0 ? TrendingDown : TrendingUp;
    const signed = (value: number) => `${value > 0 ? '+' : ''}${value}`;

    return (
        <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            <div className="space-y-4 p-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-medium">Your recommendation</h2>
                    <PhaseBadge phase={targets.phase} />
                </div>

                <div>
                    <p className="text-xs text-muted-foreground">Eat within</p>
                    <p className="text-3xl font-semibold tracking-tight tabular-nums">
                        {targets.calorie_range[0].toLocaleString()}–
                        {targets.calorie_range[1].toLocaleString()}
                        <span className="ml-1.5 text-base font-normal text-muted-foreground">
                            kcal
                        </span>
                    </p>
                </div>

                <dl className="grid grid-cols-3 gap-2 rounded-xl bg-muted/60 p-3 text-center">
                    <Figure
                        label="Maintenance"
                        value={targets.maintenance.toLocaleString()}
                        hint={
                            targets.maintenance_source === 'measured'
                                ? 'measured'
                                : 'estimated'
                        }
                    />
                    <Figure
                        label={
                            targets.daily_difference < 0 ? 'Deficit' : 'Surplus'
                        }
                        value={signed(targets.daily_difference)}
                        hint="kcal/day"
                    />
                    <Figure
                        label="Target"
                        value={targets.calories.toLocaleString()}
                        hint="kcal/day"
                    />
                </dl>

                <p className="flex items-center gap-2 text-sm">
                    <Trend className="size-4 shrink-0 text-muted-foreground" />
                    <span>
                        Aim for{' '}
                        <span className="font-medium tabular-nums">
                            {signed(targets.weekly_change)} {targets.unit}
                        </span>{' '}
                        per week
                        {targets.weekly_change_pct !== null && (
                            <span className="text-muted-foreground">
                                {' '}
                                ({signed(targets.weekly_change_pct)}% of{' '}
                                {targets.latest_weight} {targets.unit})
                            </span>
                        )}
                    </span>
                </p>
            </div>

            <dl className="grid grid-cols-3 divide-x border-t">
                <Macro
                    label="Protein"
                    grams={targets.protein_g}
                    range={targets.protein_range}
                    note="at least"
                    className="text-sky-600 dark:text-sky-400"
                />
                <Macro
                    label="Fat"
                    grams={targets.fat_g}
                    range={targets.fat_range}
                />
                <Macro
                    label="Carbs"
                    grams={targets.carbs_g}
                    range={targets.carbs_range}
                />
            </dl>

            <p className="flex items-start gap-2 border-t bg-muted/40 px-5 py-3 text-xs text-muted-foreground">
                {targets.is_adaptive ? (
                    <>
                        <Activity className="mt-px size-3.5 shrink-0" />
                        {targets.maintenance_source === 'measured'
                            ? `Week ${targets.current_week}: maintenance is measured from your weigh-ins and diary.`
                            : `Week ${targets.current_week}: log at least 4 days of calories per week so maintenance can be measured.`}
                    </>
                ) : (
                    <>
                        <CalendarClock className="mt-px size-3.5 shrink-0" />
                        Week {targets.current_week} of 3 on the estimate. From{' '}
                        {dayLabel(targets.adapts_from, today)} your targets
                        adapt to your weigh-ins and diary.
                    </>
                )}
            </p>
        </section>
    );
}

function Figure({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <div>
            <dt className="text-[11px] text-muted-foreground">{label}</dt>
            <dd className="text-sm font-semibold tabular-nums">{value}</dd>
            <dd className="text-[11px] text-muted-foreground">{hint}</dd>
        </div>
    );
}

function Macro({
    label,
    grams,
    range,
    note,
    className,
}: {
    label: string;
    grams: number;
    range: [number, number];
    note?: string;
    className?: string;
}) {
    return (
        <div className="px-3 py-3 text-center">
            <dt className="text-[11px] text-muted-foreground">
                {label}
                {note && <span className="sr-only"> ({note})</span>}
            </dt>
            <dd
                className={cn(
                    'text-base font-semibold tabular-nums',
                    className,
                )}
            >
                {Math.round(grams)}g
            </dd>
            <dd className="text-[11px] text-muted-foreground tabular-nums">
                {range[0]}–{range[1]}g
            </dd>
        </div>
    );
}
