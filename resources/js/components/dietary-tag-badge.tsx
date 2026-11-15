import { dietaryTagIcons, dietaryTagLabels } from '@/lib/dietary-tag';
import { cn } from '@/lib/utils';
import type { DietaryTagValue } from '@/types';

export default function DietaryTagBadge({
    value,
    className,
}: {
    value: DietaryTagValue;
    className?: string;
}) {
    const Icon = dietaryTagIcons[value];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md border border-emerald-600/20 bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-950/40 dark:text-emerald-300',
                className,
            )}
        >
            <Icon className="size-3" />
            {dietaryTagLabels[value]}
        </span>
    );
}
