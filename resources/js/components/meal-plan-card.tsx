import { router } from '@inertiajs/react';
import { CheckCheck, RefreshCw, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    confirm,
    destroy,
    store,
} from '@/actions/App/Http/Controllers/MealPlanController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatGrams } from '@/lib/nutrition';
import { cn } from '@/lib/utils';
import type { MealPlanSummary, MealPlannerOption } from '@/types';

/**
 * Generate a day of planned meals, then confirm or discard them. Planned meals don't count
 * toward the day's totals until they're marked as eaten.
 */
export default function MealPlanCard({
    plan,
    date,
}: {
    plan: MealPlanSummary;
    date: string;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const hasPlan = plan.pending_count > 0;

    const visitOptions = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
    };

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
                            disabled={processing}
                            onClick={() =>
                                router.visit(confirm(date), visitOptions)
                            }
                        >
                            <CheckCheck />
                            Mark all eaten
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={processing}
                            onClick={() =>
                                router.visit(destroy(date), visitOptions)
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
                    disabled={processing}
                    onClick={() => setOpen(true)}
                >
                    {hasPlan ? <RefreshCw /> : <Sparkles />}
                    {hasPlan ? 'Regenerate' : 'Plan my day'}
                </Button>
            </div>

            <GeneratePlanDialog
                key={String(open)}
                open={open}
                onOpenChange={setOpen}
                date={date}
                planners={plan.planners}
                replacing={hasPlan}
            />
        </section>
    );
}

function GeneratePlanDialog({
    open,
    onOpenChange,
    date,
    planners,
    replacing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    date: string;
    planners: MealPlannerOption[];
    replacing: boolean;
}) {
    const [planner, setPlanner] =
        useState<MealPlannerOption['value']>('search');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const generate = () =>
        router.visit(store(date), {
            data: { planner },
            preserveScroll: true,
            onStart: () => {
                setProcessing(true);
                setError(undefined);
            },
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: (errors) => setError(errors.meal_plan ?? errors.planner),
        });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Plan my day</DialogTitle>
                    <DialogDescription>
                        Fills the meals you haven't eaten yet to fit what's left
                        of your goal.
                        {replacing && ' This replaces the current plan.'}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2" role="radiogroup">
                    {planners.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            role="radio"
                            aria-checked={planner === option.value}
                            disabled={!option.available}
                            onClick={() => setPlanner(option.value)}
                            className={cn(
                                'rounded-xl border px-4 py-3 text-left text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                                planner === option.value
                                    ? 'border-primary bg-primary/5'
                                    : 'hover:bg-accent',
                            )}
                        >
                            <span className="block font-medium">
                                {option.label}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {option.value === 'search'
                                    ? 'Instant. Searches your recipes for the best fit.'
                                    : option.available
                                      ? 'Asks Claude to choose. Slower; may not always work.'
                                      : 'Set ANTHROPIC_API_KEY to enable.'}
                            </span>
                        </button>
                    ))}
                </div>

                <InputError message={error} />

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button onClick={generate} disabled={processing}>
                        {processing ? 'Planning…' : 'Generate'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
