/**
 * Shift a `YYYY-MM-DD` date by whole days without touching local timezones.
 */
export function shiftDate(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);
    const shifted = new Date(Date.UTC(year, month - 1, day + days));

    return shifted.toISOString().slice(0, 10);
}

/**
 * "Today", "Yesterday", "Tomorrow", or e.g. "Wed, Nov 12".
 */
export function dayLabel(date: string, today: string): string {
    if (date === today) {
        return 'Today';
    }

    if (date === shiftDate(today, -1)) {
        return 'Yesterday';
    }

    if (date === shiftDate(today, 1)) {
        return 'Tomorrow';
    }

    return formatDay(date, today);
}

/**
 * Always the calendar date, e.g. "Sat, Nov 15" (with the year when it isn't the current one).
 */
export function formatDay(date: string, today: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day)).toLocaleDateString(
        undefined,
        {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            ...(String(year) !== today.slice(0, 4) ? { year: 'numeric' } : {}),
            timeZone: 'UTC',
        },
    );
}

/**
 * Parse a decimal string from the API, treating null as zero.
 */
export function grams(value: string | number | null | undefined): number {
    return value === null || value === undefined ? 0 : Number(value);
}

/**
 * Format grams for display: whole numbers without a trailing ".0".
 */
export function formatGrams(value: number): string {
    return `${Number.isInteger(value) ? value : value.toFixed(1)}g`;
}

/**
 * Format a servings count, e.g. "1 serving", "1.5 servings".
 */
export function formatServings(servings: number): string {
    return `${Number(servings.toFixed(2))} ${servings === 1 ? 'serving' : 'servings'}`;
}
