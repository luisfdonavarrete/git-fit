import { router } from '@inertiajs/react';
import { Pencil, Scale, TrendingDown, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { update } from '@/actions/App/Http/Controllers/DailyCheckInController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dayLabel } from '@/lib/nutrition';
import { cn } from '@/lib/utils';
import type { CoachPhaseValue, DailyCheckIn } from '@/types';

/**
 * The day's weigh-in with the 7-day average trend the coach works from.
 */
export default function WeighInCard({
    checkIn,
    date,
    today,
    phase,
}: {
    checkIn: DailyCheckIn;
    date: string;
    today: string;
    phase: CoachPhaseValue | null;
}) {
    const [editing, setEditing] = useState(checkIn.weight === null);
    const [weight, setWeight] = useState(checkIn.weight?.toString() ?? '');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const change =
        checkIn.average_weight !== null &&
        checkIn.previous_average_weight !== null
            ? Math.round(
                  (checkIn.average_weight - checkIn.previous_average_weight) *
                      10,
              ) / 10
            : null;
    const onTrack =
        change === null || phase === null || phase === 'maintain'
            ? null
            : phase === 'cut'
              ? change <= 0
              : change >= 0;

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.visit(update(date), {
            data: { weight },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errors) => setError(errors.weight),
            onSuccess: () => {
                setError(undefined);
                setEditing(weight === '');
            },
        });
    };

    return (
        <section className="flex flex-col rounded-2xl border bg-card p-5 shadow-xs">
            <div className="flex items-center justify-between">
                <h2 className="flex items-center gap-2 text-sm font-medium">
                    <Scale className="size-4 text-muted-foreground" />
                    Weigh-in
                </h2>
                <span className="text-xs text-muted-foreground">
                    {dayLabel(date, today)}
                </span>
            </div>

            {editing ? (
                <form onSubmit={save} className="mt-4 space-y-2">
                    <div className="flex gap-2">
                        <div className="relative flex-1">
                            <Input
                                type="number"
                                inputMode="decimal"
                                step="0.1"
                                value={weight}
                                onChange={(e) => setWeight(e.target.value)}
                                placeholder="0.0"
                                aria-label={`Weight in ${checkIn.unit}`}
                                className="h-11 pr-12 text-lg font-semibold tabular-nums"
                            />
                            <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground">
                                {checkIn.unit}
                            </span>
                        </div>
                        <Button
                            type="submit"
                            className="h-11"
                            disabled={processing || weight === ''}
                        >
                            Save
                        </Button>
                    </div>
                    {error && (
                        <p className="text-xs text-red-600 dark:text-red-400">
                            {error}
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Weigh yourself each morning, after the bathroom and
                        before eating.
                    </p>
                </form>
            ) : (
                <button
                    type="button"
                    onClick={() => setEditing(true)}
                    className="group mt-3 flex items-baseline gap-1.5 text-left outline-none"
                >
                    <span className="text-3xl font-semibold tracking-tight tabular-nums">
                        {checkIn.weight}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        {checkIn.unit}
                    </span>
                    <Pencil className="ml-1 size-3.5 self-center text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100" />
                </button>
            )}

            <dl className="mt-auto grid grid-cols-2 gap-3 border-t pt-4 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">
                        7-day average
                    </dt>
                    <dd className="font-semibold tabular-nums">
                        {checkIn.average_weight === null
                            ? '—'
                            : `${checkIn.average_weight} ${checkIn.unit}`}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        vs previous 7 days
                    </dt>
                    <dd
                        className={cn(
                            'flex items-center gap-1 font-semibold tabular-nums',
                            onTrack === true &&
                                'text-emerald-600 dark:text-emerald-400',
                            onTrack === false &&
                                'text-amber-600 dark:text-amber-400',
                        )}
                    >
                        {change === null ? (
                            '—'
                        ) : (
                            <>
                                {change < 0 ? (
                                    <TrendingDown className="size-4" />
                                ) : (
                                    <TrendingUp className="size-4" />
                                )}
                                {change > 0 ? '+' : ''}
                                {change} {checkIn.unit}
                            </>
                        )}
                    </dd>
                </div>
            </dl>
        </section>
    );
}
