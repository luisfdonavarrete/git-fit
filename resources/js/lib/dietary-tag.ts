import { Carrot, Leaf, MilkOff, WheatOff } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { DietaryTagValue } from '@/types';

export const dietaryTagIcons: Record<DietaryTagValue, LucideIcon> = {
    vegan: Leaf,
    vegetarian: Carrot,
    gluten_free: WheatOff,
    dairy_free: MilkOff,
};

export const dietaryTagLabels: Record<DietaryTagValue, string> = {
    vegan: 'Vegan',
    vegetarian: 'Vegetarian',
    gluten_free: 'Gluten-free',
    dairy_free: 'Dairy-free',
};
