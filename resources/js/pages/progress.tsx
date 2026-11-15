import { Head, Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { edit as coachEdit } from '@/actions/App/Http/Controllers/CoachProfileController';
import { show } from '@/actions/App/Http/Controllers/ProgressController';
import CoachTargetsCard from '@/components/coach-targets-card';
import { Button } from '@/components/ui/button';
import WeightTrendChart from '@/components/weight-trend-chart';
import { cn } from '@/lib/utils';
import type { CoachTargets, ProgressMonth, ProgressWeek } from '@/types';

type Props = {
    startedOn: string;
    today: string;
    unit: string;
    startingWeight: number;
    targets: CoachTargets;
    weeks: ProgressWeek[];
    months: ProgressMonth[];
};

function shortDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day)).toLocaleDateString(
        undefined,
        { month: 'short', day: 'numeric', timeZone: 'UTC' },
    );
}

function signed(value: number | null, digits = 1): string {
    if (value === null) {
        return '—';
    }

    return `${value > 0 ? '+' : ''}${value.toFixed(digits)}`;
}

export default function Progress({
    startedOn,
    today,
    unit,
    startingWeight,
    targets,
    weeks,
    months,
}: Props) {
    return (
        <>
            <Head title="Progress" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Progress
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Week {targets.current_week} since{' '}
                            {shortDate(startedOn)} · weekly averages from your
                            daily weigh-ins and diary
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={coachEdit()}>
                            <Sparkles />
                            Coach settings
                        </Link>
                    </Button>
                </header>

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="space-y-6">
                        <section className="rounded-2xl border bg-card p-5 shadow-xs">
                            <h2 className="mb-4 text-sm font-medium">
                                Average weight by week ({unit})
                            </h2>
                            <WeightTrendChart
                                weeks={weeks}
                                startingWeight={startingWeight}
                                weeklyGoalChange={targets.weekly_change}
                                unit={unit}
                            />
                        </section>

                        <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
                            <h2 className="border-b px-5 py-3 text-sm font-medium">
                                Weekly check-ins
                            </h2>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Week
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Avg weight
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Change
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Avg kcal
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Measured TDEE
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Target
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {[...weeks].reverse().map((week) => (
                                            <tr
                                                key={week.number}
                                                className={cn(
                                                    week.is_current &&
                                                        'bg-primary/5',
                                                )}
                                            >
                                                <td className="px-4 py-2.5">
                                                    <span className="font-medium">
                                                        Week {week.number}
                                                    </span>
                                                    {week.is_current && (
                                                        <span className="ml-2 rounded-full bg-primary/10 px-1.5 py-0.5 text-[11px] font-medium text-primary">
                                                            Current
                                                        </span>
                                                    )}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {shortDate(
                                                            week.starts_on,
                                                        )}{' '}
                                                        –{' '}
                                                        {shortDate(
                                                            week.ends_on,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {week.avg_weight === null
                                                        ? '—'
                                                        : week.avg_weight.toFixed(
                                                              1,
                                                          )}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {week.weigh_in_days}/7
                                                        days
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {signed(week.change)}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {week.avg_calories?.toLocaleString() ??
                                                        '—'}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {week.calorie_days}/7
                                                        days
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {week.measured_tdee?.toLocaleString() ?? (
                                                        <span className="text-xs text-muted-foreground">
                                                            {week.number === 1
                                                                ? 'n/a (week 1)'
                                                                : '—'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {week.target_calories?.toLocaleString() ??
                                                        '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <p className="border-t px-5 py-3 text-xs text-muted-foreground">
                                Measured TDEE needs weigh-ins in both weeks and
                                at least 4 days of calories. Week 1 is skipped
                                because it compares against a single starting
                                weigh-in.
                            </p>
                        </section>
                    </div>

                    <aside className="space-y-6 lg:sticky lg:top-6">
                        <CoachTargetsCard targets={targets} today={today} />

                        <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
                            <h2 className="border-b px-5 py-3 text-sm font-medium">
                                By month
                            </h2>
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            Month
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Actual
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Goal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {months.map((month) => (
                                        <tr key={month.month}>
                                            <td className="px-4 py-2.5">
                                                {month.label}
                                                <span className="block text-xs text-muted-foreground">
                                                    {month.weeks} wk
                                                </span>
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                {signed(month.actual_change)}{' '}
                                                {unit}
                                            </td>
                                            <td className="px-4 py-2.5 text-right text-muted-foreground tabular-nums">
                                                {signed(month.goal_change)}{' '}
                                                {unit}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

Progress.layout = {
    breadcrumbs: [{ title: 'Progress', href: show() }],
};
