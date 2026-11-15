import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { update } from '@/actions/App/Http/Controllers/DailyCheckInController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/**
 * Type the day's real calorie total when some of it was eaten outside the recipe diary.
 */
export default function CaloriesOverrideDialog({
    date,
    diaryCalories,
    override,
    trigger,
}: {
    date: string;
    diaryCalories: number;
    override: number | null;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [calories, setCalories] = useState(override?.toString() ?? '');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    const save = (value: string) =>
        router.visit(update(date), {
            data: { calories: value },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errors) => setError(errors.calories),
            onSuccess: () => setOpen(false),
        });

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setCalories(override?.toString() ?? '');
                setError(undefined);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Day's calorie total</DialogTitle>
                    <DialogDescription>
                        Ate something that isn't in your diary? Enter the whole
                        day's total. The coach uses this instead of the diary's{' '}
                        {diaryCalories.toLocaleString()} kcal.
                    </DialogDescription>
                </DialogHeader>
                <div className="relative">
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={0}
                        value={calories}
                        onChange={(e) => setCalories(e.target.value)}
                        aria-label="Total calories"
                        className="pr-12"
                        autoFocus
                    />
                    <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground">
                        kcal
                    </span>
                </div>
                <InputError message={error} />
                <DialogFooter className="gap-2 sm:justify-between">
                    {override !== null ? (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => save('')}
                            disabled={processing}
                        >
                            Use diary total
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button
                        type="button"
                        onClick={() => save(calories)}
                        disabled={processing || calories === ''}
                    >
                        Save total
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
