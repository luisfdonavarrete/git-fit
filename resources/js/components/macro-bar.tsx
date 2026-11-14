import { cn } from '@/lib/utils';

const macros = [
    { key: 'protein', label: 'Protein', kcalPerGram: 4, color: 'bg-sky-500' },
    { key: 'carbs', label: 'Carbs', kcalPerGram: 4, color: 'bg-amber-400' },
    { key: 'fat', label: 'Fat', kcalPerGram: 9, color: 'bg-violet-500' },
] as const;

/**
 * Share of energy from protein, carbs and fat, shown as a stacked bar.
 */
export default function MacroBar({
    protein,
    carbs,
    fat,
    className,
}: {
    protein: number;
    carbs: number;
    fat: number;
    className?: string;
}) {
    const grams = { protein, carbs, fat };
    const kcal = macros.map((m) => grams[m.key] * m.kcalPerGram);
    const total = kcal.reduce((sum, value) => sum + value, 0);

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex h-2 overflow-hidden rounded-full bg-muted">
                {total > 0 &&
                    macros.map((m, i) => (
                        <div
                            key={m.key}
                            className={cn('h-full transition-all', m.color)}
                            style={{ width: `${(kcal[i] / total) * 100}%` }}
                        />
                    ))}
            </div>
            <ul className="grid grid-cols-3 gap-2 text-xs">
                {macros.map((m, i) => (
                    <li key={m.key} className="space-y-0.5">
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <span
                                className={cn('size-2 rounded-full', m.color)}
                            />
                            {m.label}
                        </span>
                        <span className="block font-medium text-foreground tabular-nums">
                            {grams[m.key] ? `${grams[m.key]}g` : '—'}
                            {total > 0 && grams[m.key] > 0 && (
                                <span className="ml-1 font-normal text-muted-foreground">
                                    {Math.round((kcal[i] / total) * 100)}%
                                </span>
                            )}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
