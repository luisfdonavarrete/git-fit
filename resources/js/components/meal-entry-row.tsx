import { router, usePage } from '@inertiajs/react';
import {
    ArrowRightLeft,
    Bot,
    Check,
    MoreHorizontal,
    Pencil,
    RefreshCw,
    Trash2,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    confirm,
    destroy,
    update,
} from '@/actions/App/Http/Controllers/MealEntryController';
import { swap } from '@/actions/App/Http/Controllers/MealPlanController';
import CalorieRangeBadge from '@/components/calorie-range-badge';
import ServingsStepper from '@/components/servings-stepper';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { mealTypeIcons, mealTypeLabels } from '@/lib/meal-type';
import { formatGrams, formatServings, grams } from '@/lib/nutrition';
import { cn } from '@/lib/utils';
import type {
    DiaryEntry,
    DiaryMealType,
    MealPlanSummary,
    MealPlannerOption,
} from '@/types';

const diarySections: DiaryMealType[] = [
    'breakfast',
    'lunch',
    'dinner',
    'snack',
];

export default function MealEntryRow({
    entry,
    mealType,
}: {
    entry: DiaryEntry;
    mealType: DiaryMealType;
}) {
    const [editing, setEditing] = useState(false);
    const servings = Number(entry.servings);

    const move = (target: DiaryMealType) =>
        router.visit(update(entry.id), {
            data: { meal_type: target, servings },
            preserveScroll: true,
        });

    const remove = () =>
        router.visit(destroy(entry.id), { preserveScroll: true });

    const isPending = entry.status === 'pending';
    const markEaten = () =>
        router.visit(confirm(entry.id), { preserveScroll: true });

    const { mealPlan } = usePage<{ mealPlan?: MealPlanSummary }>().props;
    const aiAvailable =
        mealPlan?.planners.some(
            (planner) => planner.value === 'claude' && planner.available,
        ) ?? false;
    const [swapping, setSwapping] = useState(false);
    const swapFor = (planner: MealPlannerOption['value']) =>
        router.visit(swap(entry.id), {
            data: { planner },
            preserveScroll: true,
            onStart: () => setSwapping(true),
            onFinish: () => setSwapping(false),
            onError: (errors) =>
                toast.error(
                    errors.meal_plan ??
                        errors.planner ??
                        'Could not swap this meal.',
                ),
        });

    return (
        <li
            className={cn(
                'group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/40',
                isPending && 'bg-violet-500/[0.03]',
            )}
        >
            <div
                className={cn(
                    'size-11 shrink-0 overflow-hidden rounded-lg bg-muted',
                    isPending &&
                        'opacity-60 outline-1 outline-violet-500/50 outline-dashed',
                )}
            >
                {entry.image_url ? (
                    <img
                        src={entry.image_url}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center">
                        <UtensilsCrossed className="size-4 text-muted-foreground/50" />
                    </div>
                )}
            </div>

            <button
                type="button"
                onClick={() => setEditing(true)}
                className="min-w-0 flex-1 text-left outline-none"
            >
                <span className="block truncate text-sm font-medium">
                    {entry.name}
                </span>
                <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    {isPending && (
                        <span className="rounded-full bg-violet-500/10 px-1.5 text-[11px] font-medium text-violet-600 dark:text-violet-400">
                            Planned
                        </span>
                    )}
                    <CalorieRangeBadge
                        value={entry.calorie_range}
                        className="px-1.5 py-0 text-[11px]"
                    />
                    {formatServings(servings)}
                </span>
            </button>

            <div
                className={cn(
                    'text-right tabular-nums',
                    isPending && 'opacity-60',
                )}
            >
                <span className="block text-sm font-semibold">
                    {entry.calories}
                </span>
                <span className="block text-xs text-sky-600 dark:text-sky-400">
                    {formatGrams(grams(entry.protein_g))} P
                </span>
            </div>

            {isPending && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 rounded-full text-muted-foreground"
                    onClick={() => swapFor('search')}
                    disabled={swapping}
                    aria-label={`Swap ${entry.name} for another recipe`}
                    title="Swap for another recipe"
                >
                    <RefreshCw className={cn(swapping && 'animate-spin')} />
                </Button>
            )}

            {isPending && (
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8 shrink-0 rounded-full text-violet-600 dark:text-violet-400"
                    onClick={markEaten}
                    aria-label={`I ate ${entry.name}`}
                    title="I ate this"
                >
                    <Check />
                </Button>
            )}

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 shrink-0 text-muted-foreground opacity-100 data-[state=open]:opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
                        aria-label={`Options for ${entry.name}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-44">
                    {isPending && (
                        <>
                            <DropdownMenuItem onSelect={markEaten}>
                                <Check />I ate this
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => swapFor('search')}
                            >
                                <RefreshCw />
                                Swap recipe
                            </DropdownMenuItem>
                            {aiAvailable && (
                                <DropdownMenuItem
                                    onSelect={() => swapFor('claude')}
                                >
                                    <Bot />
                                    Swap with AI
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                        </>
                    )}
                    <DropdownMenuItem onSelect={() => setEditing(true)}>
                        <Pencil />
                        Edit servings
                    </DropdownMenuItem>
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger>
                            <ArrowRightLeft />
                            Move to
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            {diarySections
                                .filter((section) => section !== mealType)
                                .map((section) => {
                                    const Icon = mealTypeIcons[section];

                                    return (
                                        <DropdownMenuItem
                                            key={section}
                                            onSelect={() => move(section)}
                                        >
                                            <Icon />
                                            {mealTypeLabels[section]}
                                        </DropdownMenuItem>
                                    );
                                })}
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant="destructive" onSelect={remove}>
                        <Trash2 />
                        Remove
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <EditServingsDialog
                entry={entry}
                open={editing}
                onOpenChange={setEditing}
            />
        </li>
    );
}

function EditServingsDialog({
    entry,
    open,
    onOpenChange,
}: {
    entry: DiaryEntry;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const current = Number(entry.servings);
    const [servings, setServings] = useState(current);
    const [processing, setProcessing] = useState(false);
    const ratio = servings / current;

    const save = () =>
        router.visit(update(entry.id), {
            data: { servings },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setServings(current);
                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle className="pr-6">{entry.name}</DialogTitle>
                    <DialogDescription>
                        How much did you have?
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-center justify-between gap-4">
                    <ServingsStepper value={servings} onChange={setServings} />
                    <div className="text-right tabular-nums">
                        <span className="block text-lg font-semibold">
                            {Math.round(entry.calories * ratio)} kcal
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {formatGrams(
                                Math.round(
                                    grams(entry.protein_g) * ratio * 10,
                                ) / 10,
                            )}{' '}
                            protein
                        </span>
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        onClick={save}
                        disabled={processing || servings === current}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
