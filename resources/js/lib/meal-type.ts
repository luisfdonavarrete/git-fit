import { Apple, Coffee, Dumbbell, Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { MealTypeValue } from '@/types';

export const mealTypeIcons: Record<MealTypeValue, LucideIcon> = {
    breakfast: Coffee,
    lunch: Sun,
    dinner: Moon,
    snack: Apple,
    high_protein_snack: Dumbbell,
};

export const mealTypeLabels: Record<MealTypeValue, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snack: 'Snack',
    high_protein_snack: 'High-protein snack',
};
