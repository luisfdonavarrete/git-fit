import { Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

const STEP = 0.5;
const MIN = 0.25;
const MAX = 20;

/**
 * A − [n] + control for servings, stepping by halves but accepting any value typed in.
 */
export default function ServingsStepper({
    value,
    onChange,
    id,
}: {
    value: number;
    onChange: (value: number) => void;
    id?: string;
}) {
    const clamp = (next: number) =>
        Math.min(MAX, Math.max(MIN, Math.round(next * 100) / 100));

    return (
        <div className="inline-flex items-center rounded-lg border bg-background shadow-xs">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="rounded-r-none"
                aria-label="Fewer servings"
                disabled={value <= MIN}
                onClick={() => onChange(clamp(value - STEP))}
            >
                <Minus />
            </Button>
            <input
                id={id}
                type="number"
                inputMode="decimal"
                min={MIN}
                max={MAX}
                step={0.25}
                value={value}
                onChange={(e) => {
                    const next = Number(e.target.value);

                    if (!Number.isNaN(next) && next > 0) {
                        onChange(clamp(next));
                    }
                }}
                className="h-9 w-14 [appearance:textfield] border-x bg-transparent text-center text-sm font-medium tabular-nums outline-none focus-visible:bg-accent [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="rounded-l-none"
                aria-label="More servings"
                disabled={value >= MAX}
                onClick={() => onChange(clamp(value + STEP))}
            >
                <Plus />
            </Button>
        </div>
    );
}
