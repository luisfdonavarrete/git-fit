import { mealTypeIcons, mealTypeLabels } from '@/lib/meal-type';
import { cn } from '@/lib/utils';
import type { MealTypeValue } from '@/types';

export default function MealTypeBadge({
    value,
    className,
}: {
    value: MealTypeValue;
    className?: string;
}) {
    const Icon = mealTypeIcons[value];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md border bg-background px-1.5 py-0.5 text-xs font-medium text-muted-foreground',
                className,
            )}
        >
            <Icon className="size-3" />
            {mealTypeLabels[value]}
        </span>
    );
}
