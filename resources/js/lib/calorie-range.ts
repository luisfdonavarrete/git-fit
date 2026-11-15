import type { CalorieRangeValue } from '@/types';

type RangeTone = {
    /** Soft pill: tinted background, readable text, subtle ring. */
    badge: string;
    /** Solid swatch for dots and coverage segments. */
    solid: string;
    /** Accent border for selected tiles. */
    ring: string;
};

/**
 * A light-to-hearty ramp: sky blue under 200, then greens for the lightest versions,
 * warming through amber to rose for 800+.
 */
const tones: Record<CalorieRangeValue, RangeTone> = {
    0: {
        badge: 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/25',
        solid: 'bg-sky-500',
        ring: 'ring-sky-500/60 border-sky-500/60',
    },
    200: {
        badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25',
        solid: 'bg-emerald-500',
        ring: 'ring-emerald-500/60 border-emerald-500/60',
    },
    300: {
        badge: 'bg-lime-50 text-lime-700 ring-lime-600/20 dark:bg-lime-400/10 dark:text-lime-300 dark:ring-lime-400/25',
        solid: 'bg-lime-500',
        ring: 'ring-lime-500/60 border-lime-500/60',
    },
    400: {
        badge: 'bg-yellow-50 text-yellow-800 ring-yellow-600/25 dark:bg-yellow-400/10 dark:text-yellow-200 dark:ring-yellow-400/25',
        solid: 'bg-yellow-400',
        ring: 'ring-yellow-500/60 border-yellow-500/60',
    },
    500: {
        badge: 'bg-amber-50 text-amber-800 ring-amber-600/25 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25',
        solid: 'bg-amber-500',
        ring: 'ring-amber-500/60 border-amber-500/60',
    },
    600: {
        badge: 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-400/10 dark:text-orange-300 dark:ring-orange-400/25',
        solid: 'bg-orange-500',
        ring: 'ring-orange-500/60 border-orange-500/60',
    },
    700: {
        badge: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/25',
        solid: 'bg-red-500',
        ring: 'ring-red-500/60 border-red-500/60',
    },
    800: {
        badge: 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/25',
        solid: 'bg-rose-600',
        ring: 'ring-rose-500/60 border-rose-500/60',
    },
};

export function rangeTone(value: CalorieRangeValue): RangeTone {
    return tones[value];
}

export function rangeLabel(value: CalorieRangeValue): string {
    if (value === 0) {
        return 'Under 200';
    }

    return value === 800 ? '800+' : `${value}–${value + 100}`;
}

/**
 * Whether an exact calorie count falls inside a range's band.
 */
export function caloriesFitRange(
    calories: number,
    value: CalorieRangeValue,
): boolean {
    if (value === 0) {
        return calories < 200;
    }

    return value === 800
        ? calories >= 800
        : calories >= value && calories < value + 100;
}
