import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { rangeLabel, rangeTone } from '@/lib/calorie-range';
import { cn } from '@/lib/utils';
import type { CalorieRangeOption, RecipeVersion } from '@/types';

/**
 * A seven-segment strip showing which calorie ranges a recipe covers.
 */
export default function VersionCoverage({
    ranges,
    versions,
    className,
}: {
    ranges: CalorieRangeOption[];
    versions: Pick<RecipeVersion, 'calorie_range' | 'calories'>[];
    className?: string;
}) {
    const byRange = new Map(versions.map((v) => [v.calorie_range, v]));

    return (
        <div className={cn('flex gap-1', className)}>
            {ranges.map((range) => {
                const version = byRange.get(range.value);

                return (
                    <Tooltip key={range.value}>
                        <TooltipTrigger asChild>
                            <span
                                className={cn(
                                    'h-1.5 flex-1 rounded-full transition-colors',
                                    version
                                        ? rangeTone(range.value).solid
                                        : 'bg-muted',
                                )}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {rangeLabel(range.value)} kcal ·{' '}
                            {version ? `${version.calories} kcal` : 'Not added'}
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
