import { rangeLabel, rangeTone } from '@/lib/calorie-range';
import { cn } from '@/lib/utils';
import type { CalorieRangeValue } from '@/types';

export default function CalorieRangeBadge({
    value,
    className,
}: {
    value: CalorieRangeValue;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium tabular-nums ring-1 ring-inset',
                rangeTone(value).badge,
                className,
            )}
        >
            <span
                className={cn('size-1.5 rounded-full', rangeTone(value).solid)}
            />
            {rangeLabel(value)} kcal
        </span>
    );
}
