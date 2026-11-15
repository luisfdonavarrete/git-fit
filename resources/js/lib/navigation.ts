import { ChefHat, LineChart, NotebookPen, Target } from 'lucide-react';
import { dashboard, progress } from '@/routes';
import { index as recipesIndex } from '@/routes/admin/recipes';
import { edit as coachEdit } from '@/routes/coach';
import type { NavGroup } from '@/types';

/**
 * The app's main navigation, grouped by what the user is doing:
 * - Track: record what happened (diary, weigh-ins) and review it.
 * - Plan: decide what comes next (coach targets; later meal plans and check-in reminders).
 * - Library: the recipe catalog everything draws from.
 */
export const mainNavGroups: NavGroup[] = [
    {
        title: 'Track',
        items: [
            { title: 'Food diary', href: dashboard(), icon: NotebookPen },
            { title: 'Progress', href: progress(), icon: LineChart },
        ],
    },
    {
        title: 'Plan',
        items: [{ title: 'Coach', href: coachEdit(), icon: Target }],
    },
    {
        title: 'Library',
        items: [{ title: 'Recipes', href: recipesIndex(), icon: ChefHat }],
    },
];
