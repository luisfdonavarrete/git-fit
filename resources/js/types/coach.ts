export type Option<T extends string = string> = {
    value: T;
    label: string;
    description?: string;
};

export type WeightUnitValue = 'lb' | 'kg';
export type SexValue = 'male' | 'female';
export type TrainingExperienceValue = 'beginner' | 'intermediate';
export type MainGoalValue = 'lose_fat' | 'build_muscle' | 'maintain';
export type CoachPhaseValue = 'cut' | 'maintain' | 'lean_bulk';

export type CoachProfileAnswers = {
    weight_unit: WeightUnitValue;
    sex: SexValue;
    training_experience: TrainingExperienceValue;
    main_goal: MainGoalValue;
    started_on: string;
    starting_weight: number;
    body_fat_pct: number;
    weekly_change_override: number | null;
};

export type CoachTargets = {
    current_week: number;
    phase: { value: CoachPhaseValue; label: string };
    unit: string;
    weekly_change: number;
    weekly_change_pct: number | null;
    latest_weight: number;
    maintenance: number;
    maintenance_source: 'formula' | 'measured';
    formula_maintenance: number;
    daily_difference: number;
    calories: number;
    calorie_range: [number, number];
    protein_g: number;
    protein_range: [number, number];
    fat_g: number;
    fat_range: [number, number];
    carbs_g: number;
    carbs_range: [number, number];
    adapts_from: string;
    is_adaptive: boolean;
    breakdown: CoachBreakdown;
};

/**
 * The inputs behind each target, in the user's weight unit, for showing the formulas.
 */
export type CoachBreakdown = {
    starting_weight: number;
    body_fat_pct: number;
    lean_mass: number;
    lean_mass_kcal: number;
    kcal_per_unit: number;
    activity_multiplier: number;
    rate_rule: string;
    unfloored_calories: number;
    calorie_floor: number;
    is_floored: boolean;
    protein_per_unit: [number, number, number];
    fat_share: [number, number, number];
    measured_weeks: {
        number: number;
        avg_calories: number | null;
        weight_change: number | null;
        tdee: number;
    }[];
};

export type DashboardCoach = CoachTargets & {
    day_calorie_range: [number, number] | null;
};

export type DailyCheckIn = {
    unit: string;
    weight: number | null;
    calories_override: number | null;
    average_weight: number | null;
    previous_average_weight: number | null;
};

export type ProgressWeek = {
    number: number;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
    avg_weight: number | null;
    weigh_in_days: number;
    change: number | null;
    avg_calories: number | null;
    calorie_days: number;
    measured_tdee: number | null;
    target_calories: number | null;
};

export type ProgressMonth = {
    month: string;
    label: string;
    weeks: number;
    actual_change: number;
    goal_change: number;
};
