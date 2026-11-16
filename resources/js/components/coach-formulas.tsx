import { Calculator } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { CoachTargets } from '@/types';

function num(value: number, digits = 1): string {
    return value.toLocaleString(undefined, { maximumFractionDigits: digits });
}

function pct(share: number): string {
    return `${num(share * 100, 1)}%`;
}

/**
 * Every formula behind the coach's targets, each worked through with the user's own numbers.
 */
export default function CoachFormulas({
    targets,
    className,
}: {
    targets: CoachTargets;
    className?: string;
}) {
    const b = targets.breakdown;
    const unit = targets.unit;
    const perUnit = unit === 'lbs' ? 'lb' : 'kg';
    const [proteinPer, proteinMinPer, proteinMaxPer] = b.protein_per_unit;
    const [fatShare, fatMinShare, fatMaxShare] = b.fat_share;
    const isMeasured = targets.maintenance_source === 'measured';

    return (
        <section
            id="formulas"
            className={cn(
                'scroll-mt-20 overflow-hidden rounded-2xl border bg-card shadow-xs',
                className,
            )}
        >
            <header className="flex items-start gap-3 border-b px-5 py-4 md:px-6">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Calculator className="size-4" />
                </span>
                <div>
                    <h2 className="font-medium">
                        How your targets are calculated
                    </h2>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        Each formula, worked through with your numbers for week{' '}
                        {targets.current_week}.
                    </p>
                </div>
            </header>

            <ol className="divide-y">
                <Step
                    n={1}
                    title="Lean mass"
                    formula="starting weight × (1 − body fat %)"
                    working={`${num(b.starting_weight)} × (1 − ${num(b.body_fat_pct)}%)`}
                    result={`${num(b.lean_mass)} ${unit}`}
                />

                <Step
                    n={2}
                    title="Estimated maintenance"
                    formula={`(370 + ${num(b.lean_mass_kcal)} × lean mass) × activity factor`}
                    working={`(370 + ${num(b.lean_mass_kcal)} × ${num(b.lean_mass)}) × ${b.activity_multiplier}`}
                    result={`${targets.formula_maintenance.toLocaleString()} kcal`}
                    note={`${num(b.lean_mass_kcal)} kcal per ${perUnit} of lean mass. Activity factor: 1.5 for men, 1.55 for women.`}
                />

                <Step
                    n={3}
                    title="Maintenance used"
                    formula={
                        isMeasured
                            ? `average of weekly TDEE, where TDEE = avg daily kcal − (change in avg weight × ${b.kcal_per_unit.toLocaleString()}) ÷ 7`
                            : 'the estimate from step 2'
                    }
                    working={
                        isMeasured
                            ? b.measured_weeks
                                  .map((week) => week.tdee.toLocaleString())
                                  .join(' + ') +
                              (b.measured_weeks.length > 1
                                  ? ` ÷ ${b.measured_weeks.length}`
                                  : '')
                            : undefined
                    }
                    result={`${targets.maintenance.toLocaleString()} kcal`}
                    note={
                        targets.is_adaptive
                            ? isMeasured
                                ? `${b.kcal_per_unit.toLocaleString()} kcal ≈ 1 ${perUnit} of body weight. Week 1 is skipped (water weight), and a week only counts with 4+ days of logged calories and weigh-ins.`
                                : 'From week 4 this switches to maintenance measured from your weigh-ins and diary, once a complete week (from week 2) has 4+ days of logged calories and weigh-ins.'
                            : `Weeks 1–3 use the estimate. From week 4, maintenance is measured: avg daily kcal − (change in avg weight × ${b.kcal_per_unit.toLocaleString()}) ÷ 7, averaged over complete weeks from week 2.`
                    }
                >
                    {isMeasured && (
                        <table className="mt-3 w-full max-w-md text-xs tabular-nums">
                            <thead className="text-muted-foreground">
                                <tr className="text-left">
                                    <th className="py-1 font-normal">Week</th>
                                    <th className="py-1 font-normal">
                                        Avg kcal
                                    </th>
                                    <th className="py-1 font-normal">
                                        Weight change
                                    </th>
                                    <th className="py-1 text-right font-normal">
                                        TDEE
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {b.measured_weeks.map((week) => (
                                    <tr key={week.number} className="border-t">
                                        <td className="py-1">W{week.number}</td>
                                        <td className="py-1">
                                            {week.avg_calories?.toLocaleString() ??
                                                '—'}
                                        </td>
                                        <td className="py-1">
                                            {week.weight_change === null
                                                ? '—'
                                                : `${week.weight_change > 0 ? '+' : ''}${num(week.weight_change, 2)} ${unit}`}
                                        </td>
                                        <td className="py-1 text-right font-medium">
                                            {week.tdee.toLocaleString()}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Step>

                <Step
                    n={4}
                    title="Weekly change"
                    formula={
                        targets.weekly_change_pct === null
                            ? 'your custom rate'
                            : 'rate × latest weekly average weight'
                    }
                    working={
                        targets.weekly_change_pct === null
                            ? undefined
                            : `${num(targets.weekly_change_pct, 3)}% × ${num(targets.latest_weight)}`
                    }
                    result={`${targets.weekly_change > 0 ? '+' : ''}${num(targets.weekly_change, 2)} ${unit}/week`}
                    note={b.rate_rule}
                />

                <Step
                    n={5}
                    title="Daily calories"
                    formula={`maintenance + weekly change × ${b.kcal_per_unit.toLocaleString()} ÷ 7`}
                    working={`${targets.maintenance.toLocaleString()} + (${num(targets.weekly_change, 2)} × ${b.kcal_per_unit.toLocaleString()}) ÷ 7 = ${b.unfloored_calories.toLocaleString()}`}
                    result={`${targets.calories.toLocaleString()} kcal`}
                    note={
                        b.is_floored
                            ? `Raised to the minimum of ${b.calorie_floor.toLocaleString()} kcal (1,500 for men, 1,200 for women).`
                            : `Never below ${b.calorie_floor.toLocaleString()} kcal (1,500 for men, 1,200 for women).`
                    }
                />

                <Step
                    n={6}
                    title="Calorie range"
                    formula="target ± 100, rounded to the nearest 50"
                    result={`${targets.calorie_range[0].toLocaleString()}–${targets.calorie_range[1].toLocaleString()} kcal`}
                    note="If the low end would fall below the minimum, the range runs from the target up to +100 instead."
                />

                <Step
                    n={7}
                    title="Protein"
                    formula={`g per ${perUnit} × starting weight`}
                    working={`${num(proteinPer, 2)} × ${num(b.starting_weight)}`}
                    result={`${num(targets.protein_g)} g`}
                    note={`Range: ${num(proteinMinPer, 2)}–${num(proteinMaxPer, 2)} g per ${perUnit} = ${targets.protein_range[0]}–${targets.protein_range[1]} g. The g per ${perUnit} depends on sex and body fat; protein is a minimum.`}
                />

                <Step
                    n={8}
                    title="Fat"
                    formula="share of calories × daily calories ÷ 9 kcal/g"
                    working={`${pct(fatShare)} × ${targets.calories.toLocaleString()} ÷ 9`}
                    result={`${num(targets.fat_g)} g`}
                    note={`Range: ${pct(fatMinShare)}–${pct(fatMaxShare)} of calories = ${targets.fat_range[0]}–${targets.fat_range[1]} g, rounded to 10.`}
                />

                <Step
                    n={9}
                    title="Carbs"
                    formula="(daily calories − protein × 4 − fat × 9) ÷ 4 kcal/g"
                    working={`(${targets.calories.toLocaleString()} − ${num(targets.protein_g)} × 4 − ${num(targets.fat_g)} × 9) ÷ 4`}
                    result={`${num(targets.carbs_g)} g`}
                    note={`Range: ±20%, rounded to 10 = ${targets.carbs_range[0]}–${targets.carbs_range[1]} g. Carbs fill whatever calories are left.`}
                />
            </ol>

            <div className="space-y-3 border-t bg-muted/30 px-5 py-4 md:px-6">
                <h3 className="text-sm font-medium">On the daily page</h3>
                <dl className="grid gap-3 text-sm sm:grid-cols-3">
                    <DailyFormula
                        term="Calories left"
                        formula="daily calories − eaten"
                        hint="Eaten is your diary total, or the total you typed for the day."
                    />
                    <DailyFormula
                        term="7-day average"
                        formula="mean of weigh-ins over the last 7 days"
                        hint="Days without a weigh-in are left out, not filled in."
                    />
                    <DailyFormula
                        term="Weekly trend"
                        formula="this 7-day average − the 7 days before"
                        hint={
                            targets.phase.value === 'maintain'
                                ? 'Shown on the weigh-in card.'
                                : `On track when it's ${targets.phase.value === 'cut' ? 'zero or falling' : 'zero or rising'}.`
                        }
                    />
                </dl>
            </div>
        </section>
    );
}

function Step({
    n,
    title,
    formula,
    working,
    result,
    note,
    children,
}: {
    n: number;
    title: string;
    formula: string;
    working?: string;
    result: string;
    note?: string;
    children?: ReactNode;
}) {
    return (
        <li className="grid gap-2 px-5 py-4 md:grid-cols-[12rem_1fr_auto] md:gap-6 md:px-6">
            <p className="flex items-baseline gap-2 text-sm font-medium">
                <span className="text-xs text-muted-foreground tabular-nums">
                    {n}.
                </span>
                {title}
            </p>
            <div className="min-w-0 space-y-1">
                <p className="font-mono text-xs break-words">{formula}</p>
                {working && (
                    <p className="font-mono text-xs break-words text-muted-foreground">
                        = {working}
                    </p>
                )}
                {note && (
                    <p className="text-xs text-muted-foreground">{note}</p>
                )}
                {children}
            </div>
            <p className="text-sm font-semibold whitespace-nowrap tabular-nums md:text-right">
                {result}
            </p>
        </li>
    );
}

function DailyFormula({
    term,
    formula,
    hint,
}: {
    term: string;
    formula: string;
    hint: string;
}) {
    return (
        <div>
            <dt className="font-medium">{term}</dt>
            <dd className="font-mono text-xs">{formula}</dd>
            <dd className="mt-0.5 text-xs text-muted-foreground">{hint}</dd>
        </div>
    );
}
