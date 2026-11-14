import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { store } from '@/actions/App/Http/Controllers/NutritionGoalController';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { NutritionGoal } from '@/types';

export default function NutritionGoalDialog({
    goal,
    trigger,
}: {
    goal: NutritionGoal | null;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Daily goal</DialogTitle>
                    <DialogDescription>
                        Applies from today on. Past days keep the goal they had.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor="goal-calories">
                                        Calories
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="goal-calories"
                                            name="calories"
                                            type="number"
                                            inputMode="numeric"
                                            min={800}
                                            max={10000}
                                            step={10}
                                            required
                                            defaultValue={
                                                goal?.calories ?? 2000
                                            }
                                            className="pr-11"
                                        />
                                        <Unit>kcal</Unit>
                                    </div>
                                    <InputError message={errors.calories} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="goal-protein">
                                        Protein
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="goal-protein"
                                            name="protein_g"
                                            type="number"
                                            inputMode="decimal"
                                            min={0}
                                            max={500}
                                            step={1}
                                            required
                                            defaultValue={
                                                goal
                                                    ? Number(goal.protein_g)
                                                    : 150
                                            }
                                            className="pr-7"
                                        />
                                        <Unit>g</Unit>
                                    </div>
                                    <InputError message={errors.protein_g} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save goal
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Unit({ children }: { children: ReactNode }) {
    return (
        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground">
            {children}
        </span>
    );
}
