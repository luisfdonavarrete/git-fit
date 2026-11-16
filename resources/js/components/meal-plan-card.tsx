import { router } from '@inertiajs/react';
import { Bot, CheckCheck, RefreshCw, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    confirm,
    destroy,
    store,
} from '@/actions/App/Http/Controllers/MealPlanController';
import { Button } from '@/components/ui/button';
import { formatGrams } from '@/lib/nutrition';
import type { MealPlanSummary, MealPlannerOption } from '@/types';

type Action = MealPlannerOption['value'] | 'confirm' | 'discard';

/**
 * Generate a day of planned meals in one click (from the recipe library or with AI), then
 * confirm or discard them. Planned meals don't count toward the day's totals until eaten.
 */
export default function MealPlanCard({
    plan,
    date,
}: {
    plan: MealPlanSummary;
    date: string;
}) {
    const [running, setRunning] = useState<Action | null>(null);
    const hasPlan = plan.pending_count > 0;
    const ai = plan.planners.find((planner) => planner.value === 'claude');

    const visitOptions = (action: Action) => ({
        preserveScroll: true,
        onStart: () => setRunning(action),
        onFinish: () => setRunning(null),
    });

    const generate = (planner: MealPlannerOption['value']) =>
        router.visit(store(date), {
            ...visitOptions(planner),
            data: { planner },
            onError: (errors) =>
                toast.error(
                    errors.meal_plan ??
                        errors.planner ??
                        'Could not plan this day.',
                ),
        });

    return (
        <section className="flex flex-col gap-3 rounded-2xl border border-dashed bg-card/60 px-4 py-3 sm:flex-row sm:items-center">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                <Sparkles className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <h2 className="font-medium">Meal plan</h2>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {hasPlan
                        ? `${plan.pending_count} planned · ${plan.planned.calories.toLocaleString()} kcal · ${formatGrams(plan.planned.protein_g)} protein — mark meals as eaten to count them.`
                        : 'Get a breakfast, lunch, dinner and two snacks that fit your goal.'}
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {hasPlan && (
                    <>
                        <Button
                            size="sm"
                            disabled={running !== null}
                            onClick={() =>
                                router.visit(
                                    confirm(date),
                                    visitOptions('confirm'),
                                )
                            }
                        >
                            <CheckCheck />
                            Mark all eaten
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={running !== null}
                            onClick={() =>
                                router.visit(
                                    destroy(date),
                                    visitOptions('discard'),
                                )
                            }
                        >
                            <Trash2 />
                            Discard
                        </Button>
                    </>
                )}
                <Button
                    size="sm"
                    variant={hasPlan ? 'secondary' : 'default'}
                    disabled={running !== null}
                    onClick={() => generate('search')}
                >
                    {hasPlan ? <RefreshCw /> : <Sparkles />}
                    {running === 'search'
                        ? 'Planning…'
                        : hasPlan
                          ? 'Regenerate'
                          : 'Plan my day'}
                </Button>
                {ai && (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={running !== null || !ai.available}
                        title={
                            ai.available
                                ? 'Experimental: let Claude choose the meals'
                                : 'Set ANTHROPIC_API_KEY to enable'
                        }
                        onClick={() => generate('claude')}
                    >
                        <Bot />
                        {running === 'claude'
                            ? 'Asking AI…'
                            : hasPlan
                              ? 'Regenerate with AI'
                              : 'Generate with AI'}
                    </Button>
                )}
            </div>
        </section>
    );
}
