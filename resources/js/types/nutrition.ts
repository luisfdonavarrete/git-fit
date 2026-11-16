import type { CalorieRangeValue, MealTypeValue } from './recipes';

export type DiaryMealType = Extract<
    MealTypeValue,
    'breakfast' | 'lunch' | 'dinner' | 'snack'
>;

export type Macro =
    | 'calories'
    | 'protein_g'
    | 'carbs_g'
    | 'fat_g'
    | 'fiber_g'
    | 'sugar_g'
    | 'sodium_mg';

export type NutritionSummary = {
    date: string;
    totals: Record<Macro, number>;
    targets: Record<Macro, number | null>;
};

export type MealEntryStatus = 'pending' | 'eaten';

export type DiaryEntry = {
    id: number;
    name: string;
    status: MealEntryStatus;
    servings: string;
    calorie_range: CalorieRangeValue;
    calories: number;
    protein_g: string | null;
    carbs_g: string | null;
    fat_g: string | null;
    image_url: string | null;
};

export type MealEntryRecipe = {
    description: string | null;
    image_url: string | null;
    prep_minutes: number | null;
    notes: string | null;
    ingredients: {
        id: number;
        name: string;
        quantity: string | null;
        unit: string | null;
        note: string | null;
    }[];
    steps: { id: number; instruction: string }[];
};

export type DiaryMeal = {
    type: DiaryMealType;
    label: string;
    entries: DiaryEntry[];
};

export type MealPlannerOption = {
    value: 'search' | 'claude';
    label: string;
    available: boolean;
};

export type MealPlanSummary = {
    pending_count: number;
    planned: { calories: number; protein_g: number };
    planners: MealPlannerOption[];
};

export type NutritionGoal = {
    calories: number;
    protein_g: string;
};

export type LoggableRecipe = {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    versions: {
        id: number;
        calorie_range: CalorieRangeValue;
        calories: number;
        protein_g: string | null;
        carbs_g: string | null;
        fat_g: string | null;
    }[];
};
