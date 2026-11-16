import { Head, useForm } from '@inertiajs/react';
import {
    Activity,
    CalendarClock,
    Check,
    Dumbbell,
    Flame,
    Scale,
    Sparkles,
    Target,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/CoachProfileController';
import CoachFormulas from '@/components/coach-formulas';
import { PhaseBadge } from '@/components/coach-targets-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDay } from '@/lib/nutrition';
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

function signed(value: number): string {
    return `${value > 0 ? '+' : ''}${value.toLocaleString()}`;
}

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
            profile?.weekly_change_override != null
                ? String(profile.weekly_change_override)
                : '',
    });
    const customRate = form.data.weekly_change_override !== '';
    const unitLabel = form.data.weight_unit === 'kg' ? 'kg' : 'lbs';
    const showSaveBar = profile === null || form.isDirty;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            weekly_change_override:
                data.weekly_change_override === ''
                    ? null
                    : data.weekly_change_override,
        }));
        form.submit(update(), {
            // On first setup, scroll up so the newly calculated targets are in view.
            preserveScroll: profile !== null,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <>
            <Head title="Coach" />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Coach
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Your calorie and macro targets, worked out from your
                            body stats and goal, then adjusted every week from
                            your weigh-ins and food diary.
                        </p>
                    </div>
                    {targets && <WeekStatus targets={targets} />}
                </header>

                {targets ? (
                    <Overview targets={targets} today={today} />
                ) : (
                    <HowItWorks />
                )}

                <form onSubmit={submit} className="relative">
                    <div className="divide-y rounded-2xl border bg-card shadow-xs">
                        <Section
                            title="Body stats"
                            description="As they were on your start date. They set your first estimate and your protein target, so don't update them as you lose or gain weight."
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Units"
                                    error={form.errors.weight_unit}
                                >
                                    <Segmented
                                        label="Units"
                                        value={form.data.weight_unit}
                                        options={options.weightUnits}
                                        onChange={(value) =>
                                            form.setData('weight_unit', value)
                                        }
                                    />
                                </Field>
                                <Field label="Sex" error={form.errors.sex}>
                                    <Segmented
                                        label="Sex"
                                        value={form.data.sex}
                                        options={options.sexes}
                                        onChange={(value) =>
                                            form.setData('sex', value)
                                        }
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label="Starting weight"
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
                        </Section>

                        <Section
                            title="Goal"
                            description="Together with your body fat, this decides whether the coach recommends a cut, maintenance or a lean bulk."
                        >
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
                        </Section>

                        <Section
                            title="Weekly rate"
                            description="How fast to lose or gain. The recommended rate is a percentage of your latest average weight, so it eases off as you progress."
                        >
                            <div className="grid gap-2 sm:grid-cols-2">
                                <ChoiceCard
                                    selected={!customRate}
                                    onSelect={() =>
                                        form.setData(
                                            'weekly_change_override',
                                            '',
                                        )
                                    }
                                >
                                    <span className="font-medium">
                                        Recommended
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {targets &&
                                        targets.weekly_change_pct !== null
                                            ? `${signed(targets.weekly_change)} ${targets.unit}/week (${signed(targets.weekly_change_pct)}% of body weight)`
                                            : 'Picked from your goal, body fat and experience'}
                                    </span>
                                </ChoiceCard>
                                <ChoiceCard
                                    selected={customRate}
                                    onSelect={() =>
                                        !customRate &&
                                        form.setData(
                                            'weekly_change_override',
                                            String(
                                                targets?.weekly_change ??
                                                    (form.data.weight_unit ===
                                                    'kg'
                                                        ? -0.5
                                                        : -1),
                                            ),
                                        )
                                    }
                                >
                                    <span className="font-medium">Custom</span>
                                    <span className="text-xs text-muted-foreground">
                                        Set your own weekly change
                                    </span>
                                </ChoiceCard>
                            </div>
                            {customRate && (
                                <div className="max-w-56">
                                    <Field
                                        label="Weekly change"
                                        htmlFor="weekly_change_override"
                                        error={
                                            form.errors.weekly_change_override
                                        }
                                    >
                                        <WithUnit unit={`${unitLabel}/wk`}>
                                            <Input
                                                id="weekly_change_override"
                                                type="number"
                                                inputMode="decimal"
                                                step="0.05"
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
                                    </Field>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Negative to lose, positive to gain.
                                    </p>
                                </div>
                            )}
                        </Section>
                    </div>

                    <div
                        className={cn(
                            'sticky bottom-4 z-10 mt-4 flex items-center justify-between gap-3 rounded-2xl border bg-background/95 px-4 py-3 shadow-lg backdrop-blur transition-all duration-200',
                            showSaveBar
                                ? 'translate-y-0 opacity-100'
                                : 'pointer-events-none translate-y-2 opacity-0',
                        )}
                        aria-hidden={!showSaveBar}
                    >
                        <p className="text-sm text-muted-foreground">
                            {profile
                                ? 'You have unsaved changes. Saving recalculates this week’s targets.'
                                : 'Your targets are calculated as soon as you save.'}
                        </p>
                        <div className="flex shrink-0 gap-2">
                            {profile && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => form.reset()}
                                    disabled={form.processing}
                                >
                                    Discard
                                </Button>
                            )}
                            <Button type="submit" disabled={form.processing}>
                                {profile
                                    ? 'Save and recalculate'
                                    : 'Calculate my targets'}
                            </Button>
                        </div>
                    </div>
                </form>

                {targets && <CoachFormulas targets={targets} />}
            </div>
        </>
    );
}

CoachEdit.layout = {
    breadcrumbs: [{ title: 'Coach', href: edit() }],
};

function WeekStatus({ targets }: { targets: CoachTargets }) {
    return (
        <span className="inline-flex items-center gap-2 self-start rounded-full border bg-card px-3 py-1 text-sm shadow-xs sm:self-auto">
            {targets.is_adaptive ? (
                <Activity className="size-4 text-emerald-500" />
            ) : (
                <CalendarClock className="size-4 text-muted-foreground" />
            )}
            <span className="font-medium">Week {targets.current_week}</span>
            <span className="text-muted-foreground">
                {targets.is_adaptive ? 'Adaptive' : 'Estimate'}
            </span>
        </span>
    );
}

function Overview({
    targets,
    today,
}: {
    targets: CoachTargets;
    today: string;
}) {
    const Trend = targets.weekly_change < 0 ? TrendingDown : TrendingUp;

    return (
        <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            <div className="grid gap-6 p-5 md:grid-cols-[1.1fr_1fr] md:p-6">
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-sm font-medium">
                            This week’s targets
                        </h2>
                        <PhaseBadge phase={targets.phase} />
                    </div>
                    <div>
                        <p className="text-4xl font-semibold tracking-tight tabular-nums">
                            {targets.calorie_range[0].toLocaleString()}–
                            {targets.calorie_range[1].toLocaleString()}
                            <span className="ml-2 text-base font-normal text-muted-foreground">
                                kcal / day
                            </span>
                        </p>
                        <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Trend className="size-4" />
                            Aiming for{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {signed(targets.weekly_change)} {targets.unit}
                            </span>{' '}
                            per week
                        </p>
                    </div>

                    <dl className="flex items-center gap-3 rounded-xl bg-muted/60 p-3 text-sm tabular-nums">
                        <Term
                            label="Maintenance"
                            value={targets.maintenance.toLocaleString()}
                            hint={
                                targets.maintenance_source === 'measured'
                                    ? 'measured'
                                    : 'estimated'
                            }
                        />
                        <span className="text-muted-foreground">
                            {targets.daily_difference < 0 ? '−' : '+'}
                        </span>
                        <Term
                            label={
                                targets.daily_difference < 0
                                    ? 'Deficit'
                                    : 'Surplus'
                            }
                            value={Math.abs(
                                targets.daily_difference,
                            ).toLocaleString()}
                            hint="per day"
                        />
                        <span className="text-muted-foreground">=</span>
                        <Term
                            label="Target"
                            value={targets.calories.toLocaleString()}
                            hint="kcal"
                            emphasis
                        />
                    </dl>
                </div>

                <dl className="grid grid-cols-3 gap-2 self-center">
                    <MacroTile
                        label="Protein"
                        grams={targets.protein_g}
                        range={targets.protein_range}
                        accent="bg-sky-500"
                        note="minimum"
                    />
                    <MacroTile
                        label="Fat"
                        grams={targets.fat_g}
                        range={targets.fat_range}
                        accent="bg-violet-500"
                    />
                    <MacroTile
                        label="Carbs"
                        grams={targets.carbs_g}
                        range={targets.carbs_range}
                        accent="bg-amber-400"
                    />
                </dl>
            </div>

            <AdaptiveTimeline targets={targets} today={today} />
        </section>
    );
}

function AdaptiveTimeline({
    targets,
    today,
}: {
    targets: CoachTargets;
    today: string;
}) {
    const steps = [1, 2, 3, 4];

    return (
        <div className="flex flex-col gap-3 border-t bg-muted/30 px-5 py-4 sm:flex-row sm:items-center md:px-6">
            <ol
                className="flex items-center gap-1.5"
                aria-label="Coaching weeks"
            >
                {steps.map((week) => {
                    const isAdaptiveStep = week === 4;
                    const reached = isAdaptiveStep
                        ? targets.is_adaptive
                        : targets.current_week >= week;
                    const isCurrent = isAdaptiveStep
                        ? targets.is_adaptive
                        : targets.current_week === week;

                    return (
                        <li key={week} className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'flex h-6 items-center justify-center rounded-full px-2 text-[11px] font-medium tabular-nums',
                                    reached
                                        ? isAdaptiveStep
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-foreground text-background'
                                        : 'bg-muted text-muted-foreground',
                                    isCurrent &&
                                        'ring-2 ring-ring/40 ring-offset-1 ring-offset-background',
                                )}
                            >
                                {isAdaptiveStep ? 'W4+' : `W${week}`}
                            </span>
                            {week < 4 && (
                                <span
                                    className={cn(
                                        'h-px w-4 sm:w-6',
                                        targets.current_week > week
                                            ? 'bg-foreground/40'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
            <p className="text-xs text-muted-foreground">
                {targets.is_adaptive
                    ? targets.maintenance_source === 'measured'
                        ? 'Maintenance is now measured from your weigh-ins and diary, and updates every week.'
                        : 'Log at least 4 days of calories a week, with daily weigh-ins, so maintenance can be measured.'
                    : `Weeks 1–3 use an estimate. From ${formatDay(targets.adapts_from, today)} your targets adapt to your weigh-ins and diary.`}
            </p>
        </div>
    );
}

function HowItWorks() {
    const steps = [
        {
            icon: Target,
            title: 'Tell us about you',
            body: 'Body stats, goal and training experience.',
        },
        {
            icon: Sparkles,
            title: 'Get your targets',
            body: 'A calorie range plus protein, fat and carbs.',
        },
        {
            icon: Activity,
            title: 'It adapts from week 4',
            body: 'Daily weigh-ins and your diary show your real maintenance.',
        },
    ];

    return (
        <ol className="grid gap-3 sm:grid-cols-3">
            {steps.map((step, index) => (
                <li
                    key={step.title}
                    className="flex gap-3 rounded-2xl border bg-card p-4 shadow-xs"
                >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <step.icon className="size-4" />
                    </span>
                    <div>
                        <p className="text-sm font-medium">
                            {index + 1}. {step.title}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {step.body}
                        </p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

function Term({
    label,
    value,
    hint,
    emphasis = false,
}: {
    label: string;
    value: string;
    hint: string;
    emphasis?: boolean;
}) {
    return (
        <div className="min-w-0 flex-1 text-center">
            <dt className="text-[11px] text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'font-semibold',
                    emphasis && 'text-emerald-600 dark:text-emerald-400',
                )}
            >
                {value}
            </dd>
            <dd className="text-[11px] text-muted-foreground">{hint}</dd>
        </div>
    );
}

function MacroTile({
    label,
    grams,
    range,
    accent,
    note,
}: {
    label: string;
    grams: number;
    range: [number, number];
    accent: string;
    note?: string;
}) {
    return (
        <div className="rounded-xl border p-3">
            <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <span className={cn('size-2 rounded-full', accent)} />
                {label}
            </dt>
            <dd className="mt-1 text-xl font-semibold tabular-nums">
                {Math.round(grams)}
                <span className="text-sm font-normal text-muted-foreground">
                    g
                </span>
            </dd>
            <dd className="text-[11px] text-muted-foreground tabular-nums">
                {note ? `${note} · ` : ''}
                {range[0]}–{range[1]}g
            </dd>
        </div>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-4 p-5 md:grid-cols-[15rem_1fr] md:gap-8 md:p-6">
            <div>
                <h2 className="font-medium">{title}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="min-w-0 space-y-5">{children}</div>
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

function Segmented<T extends string>({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: T;
    options: Option<T>[];
    onChange: (value: T) => void;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className="grid auto-cols-fr grid-flow-col rounded-xl bg-muted p-1 text-sm"
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
                'relative flex flex-col items-start gap-1 rounded-xl border p-3 pr-9 text-left text-sm transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                selected
                    ? 'border-primary bg-primary/[0.03] ring-1 ring-primary'
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
