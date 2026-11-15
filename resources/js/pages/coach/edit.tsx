import { Head, useForm } from '@inertiajs/react';
import { Check, Dumbbell, Flame, Scale, Sparkles } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/CoachProfileController';
import CoachTargetsCard from '@/components/coach-targets-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type {
    CoachProfileAnswers,
    CoachTargets,
    MainGoalValue,
    Option,
    SexValue,
    TrainingExperienceValue,
    WeightUnitValue,
} from '@/types';

type Props = {
    profile: CoachProfileAnswers | null;
    targets: CoachTargets | null;
    today: string;
    options: {
        weightUnits: Option<WeightUnitValue>[];
        sexes: Option<SexValue>[];
        trainingExperiences: Option<TrainingExperienceValue>[];
        mainGoals: Option<MainGoalValue>[];
    };
};

type FormData = {
    weight_unit: WeightUnitValue;
    sex: SexValue;
    training_experience: TrainingExperienceValue;
    main_goal: MainGoalValue;
    started_on: string;
    starting_weight: string;
    body_fat_pct: string;
    weekly_change_override: string;
};

const goalIcons: Record<MainGoalValue, typeof Flame> = {
    lose_fat: Flame,
    build_muscle: Dumbbell,
    maintain: Scale,
};

export default function CoachEdit({ profile, targets, today, options }: Props) {
    const form = useForm<FormData>({
        weight_unit: profile?.weight_unit ?? 'lb',
        sex: profile?.sex ?? 'male',
        training_experience: profile?.training_experience ?? 'beginner',
        main_goal: profile?.main_goal ?? 'lose_fat',
        started_on: profile?.started_on ?? today,
        starting_weight: profile ? String(profile.starting_weight) : '',
        body_fat_pct: profile ? String(profile.body_fat_pct) : '',
        weekly_change_override:
            profile?.weekly_change_override !== null &&
            profile?.weekly_change_override !== undefined
                ? String(profile.weekly_change_override)
                : '',
    });
    const manualRate = form.data.weekly_change_override !== '';
    const unitLabel = form.data.weight_unit === 'kg' ? 'kg' : 'lbs';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            weekly_change_override:
                data.weekly_change_override === ''
                    ? null
                    : data.weekly_change_override,
        }));
        form.submit(update(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Coach" />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <Sparkles className="size-5 text-primary" />
                        {profile ? 'Coach settings' : 'Set up your coach'}
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Answer a few questions and the coach works out your
                        calories and macros. From week 4 it adjusts them every
                        week using your daily weigh-ins and food diary.
                    </p>
                </header>

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <form onSubmit={submit} className="space-y-6">
                        <Step number={1} title="Your current body stats">
                            <Field
                                label="Units"
                                error={form.errors.weight_unit}
                            >
                                <ChoiceGroup
                                    name="weight_unit"
                                    value={form.data.weight_unit}
                                    options={options.weightUnits}
                                    onChange={(value) =>
                                        form.setData('weight_unit', value)
                                    }
                                />
                            </Field>

                            <Field label="Sex" error={form.errors.sex}>
                                <ChoiceGroup
                                    name="sex"
                                    value={form.data.sex}
                                    options={options.sexes}
                                    onChange={(value) =>
                                        form.setData('sex', value)
                                    }
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label="Current weight"
                                    htmlFor="starting_weight"
                                    error={form.errors.starting_weight}
                                >
                                    <WithUnit unit={unitLabel}>
                                        <Input
                                            id="starting_weight"
                                            type="number"
                                            inputMode="decimal"
                                            step="0.1"
                                            required
                                            value={form.data.starting_weight}
                                            onChange={(e) =>
                                                form.setData(
                                                    'starting_weight',
                                                    e.target.value,
                                                )
                                            }
                                            className="pr-12"
                                        />
                                    </WithUnit>
                                </Field>
                                <Field
                                    label="Body fat"
                                    htmlFor="body_fat_pct"
                                    error={form.errors.body_fat_pct}
                                >
                                    <WithUnit unit="%">
                                        <Input
                                            id="body_fat_pct"
                                            type="number"
                                            inputMode="decimal"
                                            step="0.5"
                                            required
                                            value={form.data.body_fat_pct}
                                            onChange={(e) =>
                                                form.setData(
                                                    'body_fat_pct',
                                                    e.target.value,
                                                )
                                            }
                                            className="pr-8"
                                        />
                                    </WithUnit>
                                </Field>
                                <Field
                                    label="Start date"
                                    htmlFor="started_on"
                                    error={form.errors.started_on}
                                >
                                    <Input
                                        id="started_on"
                                        type="date"
                                        required
                                        value={form.data.started_on}
                                        onChange={(e) =>
                                            form.setData(
                                                'started_on',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Coaching weeks start on this date. Your starting
                                weight and body fat set your protein and first
                                estimate, so keep them as they were on day one.
                            </p>
                        </Step>

                        <Step number={2} title="Your goal">
                            <Field
                                label="Main goal"
                                error={form.errors.main_goal}
                            >
                                <div className="grid gap-2 sm:grid-cols-3">
                                    {options.mainGoals.map((goal) => {
                                        const Icon = goalIcons[goal.value];

                                        return (
                                            <ChoiceCard
                                                key={goal.value}
                                                selected={
                                                    form.data.main_goal ===
                                                    goal.value
                                                }
                                                onSelect={() =>
                                                    form.setData(
                                                        'main_goal',
                                                        goal.value,
                                                    )
                                                }
                                            >
                                                <Icon className="size-4 text-muted-foreground" />
                                                <span className="font-medium">
                                                    {goal.label}
                                                </span>
                                            </ChoiceCard>
                                        );
                                    })}
                                </div>
                            </Field>

                            <Field
                                label="Training experience"
                                error={form.errors.training_experience}
                            >
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {options.trainingExperiences.map(
                                        (level) => (
                                            <ChoiceCard
                                                key={level.value}
                                                selected={
                                                    form.data
                                                        .training_experience ===
                                                    level.value
                                                }
                                                onSelect={() =>
                                                    form.setData(
                                                        'training_experience',
                                                        level.value,
                                                    )
                                                }
                                            >
                                                <span className="font-medium">
                                                    {level.label}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {level.description}
                                                </span>
                                            </ChoiceCard>
                                        ),
                                    )}
                                </div>
                            </Field>

                            <div className="rounded-xl border p-4">
                                <label className="flex items-start gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={manualRate}
                                        onChange={(e) =>
                                            form.setData(
                                                'weekly_change_override',
                                                e.target.checked
                                                    ? String(
                                                          targets?.weekly_change ??
                                                              (form.data
                                                                  .weight_unit ===
                                                              'kg'
                                                                  ? -0.5
                                                                  : -1),
                                                      )
                                                    : '',
                                            )
                                        }
                                        className="mt-0.5 size-4 accent-primary"
                                    />
                                    <span>
                                        <span className="block font-medium">
                                            Set my own weekly weight change
                                        </span>
                                        <span className="text-muted-foreground">
                                            Otherwise the coach picks a safe
                                            rate from your goal, body fat and
                                            experience.
                                        </span>
                                    </span>
                                </label>
                                {manualRate && (
                                    <div className="mt-3 max-w-48 pl-7">
                                        <WithUnit unit={`${unitLabel}/wk`}>
                                            <Input
                                                type="number"
                                                inputMode="decimal"
                                                step="0.05"
                                                aria-label="Weekly weight change"
                                                value={
                                                    form.data
                                                        .weekly_change_override
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'weekly_change_override',
                                                        e.target.value,
                                                    )
                                                }
                                                className="pr-16"
                                            />
                                        </WithUnit>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Negative to lose, positive to gain.
                                        </p>
                                        <InputError
                                            message={
                                                form.errors
                                                    .weekly_change_override
                                            }
                                        />
                                    </div>
                                )}
                            </div>
                        </Step>

                        <div className="flex items-center justify-end gap-3">
                            {form.recentlySuccessful && (
                                <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                    <Check className="size-4" /> Saved
                                </span>
                            )}
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                            >
                                {profile
                                    ? 'Save and recalculate'
                                    : 'Calculate my targets'}
                            </Button>
                        </div>
                    </form>

                    <aside className="lg:sticky lg:top-6">
                        {targets ? (
                            <CoachTargetsCard targets={targets} today={today} />
                        ) : (
                            <div className="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                                <Sparkles className="mb-3 size-5 text-primary" />
                                Your calorie range, protein, fat and carbs will
                                appear here once you save.
                            </div>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

CoachEdit.layout = {
    breadcrumbs: [{ title: 'Coach', href: edit() }],
};

function Step({
    number,
    title,
    children,
}: {
    number: number;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-5 rounded-2xl border bg-card p-5 shadow-xs">
            <h2 className="flex items-center gap-3 font-medium">
                <span className="flex size-7 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                    {number}
                </span>
                {title}
            </h2>
            {children}
        </section>
    );
}

function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function WithUnit({ unit, children }: { unit: string; children: ReactNode }) {
    return (
        <div className="relative">
            {children}
            <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground">
                {unit}
            </span>
        </div>
    );
}

function ChoiceGroup<T extends string>({
    name,
    value,
    options,
    onChange,
}: {
    name: string;
    value: T;
    options: Option<T>[];
    onChange: (value: T) => void;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={name}
            className="inline-flex rounded-xl bg-muted p-1 text-sm"
        >
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="radio"
                    aria-checked={value === option.value}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'rounded-lg px-4 py-1.5 font-medium transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        value === option.value
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

function ChoiceCard({
    selected,
    onSelect,
    children,
}: {
    selected: boolean;
    onSelect: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            className={cn(
                'relative flex flex-col items-start gap-1 rounded-xl border p-3 text-left text-sm transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                selected
                    ? 'border-primary ring-1 ring-primary'
                    : 'hover:bg-accent',
            )}
        >
            {selected && (
                <Check className="absolute top-3 right-3 size-4 text-primary" />
            )}
            {children}
        </button>
    );
}
