export type CalorieRangeValue = 200 | 300 | 400 | 500 | 600 | 700 | 800;

export type CalorieRangeOption = {
    value: CalorieRangeValue;
    label: string;
    available?: boolean;
};

export type MealTypeValue =
    | 'breakfast'
    | 'lunch'
    | 'dinner'
    | 'snack'
    | 'high_protein_snack';

export type MealTypeOption = {
    value: MealTypeValue;
    label: string;
};

export type RecipeMealType = {
    id: number;
    recipe_id: number;
    meal_type: MealTypeValue;
};

export type RecipeIngredient = {
    id: number;
    position: number;
    name: string;
    quantity: string | null;
    unit: string | null;
    note: string | null;
};

export type RecipeStep = {
    id: number;
    position: number;
    instruction: string;
};

export type RecipeVersion = {
    id: number;
    recipe_id: number;
    calorie_range: CalorieRangeValue;
    calories: number;
    protein_g: string | null;
    carbs_g: string | null;
    fat_g: string | null;
    fiber_g: string | null;
    sugar_g: string | null;
    sodium_mg: number | null;
    notes: string | null;
    ingredients?: RecipeIngredient[];
    steps?: RecipeStep[];
};

export type Recipe = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image_path: string | null;
    created_at: string;
    updated_at: string;
    versions?: RecipeVersion[];
    meal_types?: RecipeMealType[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
