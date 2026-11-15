import { useId, useState } from 'react';
import type { ProgressWeek } from '@/types';

const WIDTH = 640;
const HEIGHT = 220;
const PAD = { top: 16, right: 56, bottom: 28, left: 44 };

/**
 * Weekly average weight with the coach's goal pace as a dashed reference line.
 */
export default function WeightTrendChart({
    weeks,
    startingWeight,
    weeklyGoalChange,
    unit,
}: {
    weeks: ProgressWeek[];
    startingWeight: number;
    weeklyGoalChange: number;
    unit: string;
}) {
    const titleId = useId();
    const [hovered, setHovered] = useState<number | null>(null);
    const points = weeks
        .map((week) => ({ week, x: week.number, y: week.avg_weight }))
        .filter(
            (p): p is { week: ProgressWeek; x: number; y: number } =>
                p.y !== null,
        );

    if (points.length === 0) {
        return (
            <div className="flex h-48 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                Your weekly average weight appears here after your first
                weigh-ins.
            </div>
        );
    }

    const lastWeek = Math.max(weeks.length, 2);
    const goalAt = (week: number) => startingWeight + weeklyGoalChange * week;
    const values = [
        ...points.map((p) => p.y),
        startingWeight,
        goalAt(lastWeek),
    ];
    const min = Math.floor(Math.min(...values) - 1);
    const max = Math.ceil(Math.max(...values) + 1);

    const x = (week: number) =>
        PAD.left + ((week - 0) / lastWeek) * (WIDTH - PAD.left - PAD.right);
    const y = (value: number) =>
        PAD.top +
        ((max - value) / (max - min)) * (HEIGHT - PAD.top - PAD.bottom);
    const ticks = Array.from(
        { length: 4 },
        (_, i) => min + ((max - min) * i) / 3,
    );

    const path = [{ x: 0, y: startingWeight }, ...points]
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${x(p.x).toFixed(1)},${y(p.y).toFixed(1)}`,
        )
        .join(' ');
    const active =
        hovered === null ? null : (points.find((p) => p.x === hovered) ?? null);

    return (
        <figure className="relative" aria-labelledby={titleId}>
            <figcaption id={titleId} className="sr-only">
                Weekly average weight in {unit}, starting at {startingWeight},
                against the goal pace of {weeklyGoalChange} {unit} per week.
            </figcaption>
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="h-auto w-full"
                role="img"
                onMouseLeave={() => setHovered(null)}
                onMouseMove={(event) => {
                    const box = event.currentTarget.getBoundingClientRect();
                    const pointerX =
                        ((event.clientX - box.left) / box.width) * WIDTH;
                    const nearest = points.reduce((best, p) =>
                        Math.abs(x(p.x) - pointerX) <
                        Math.abs(x(best.x) - pointerX)
                            ? p
                            : best,
                    );
                    setHovered(nearest.x);
                }}
            >
                {ticks.map((tick) => (
                    <g key={tick}>
                        <line
                            x1={PAD.left}
                            x2={WIDTH - PAD.right}
                            y1={y(tick)}
                            y2={y(tick)}
                            className="stroke-border"
                            strokeWidth={1}
                        />
                        <text
                            x={PAD.left - 8}
                            y={y(tick)}
                            dy="0.32em"
                            textAnchor="end"
                            className="fill-muted-foreground text-[11px] tabular-nums"
                        >
                            {tick.toFixed(0)}
                        </text>
                    </g>
                ))}
                {Array.from({ length: lastWeek + 1 }, (_, week) => week)
                    .filter(
                        (week) =>
                            week === 0 || week % Math.ceil(lastWeek / 8) === 0,
                    )
                    .map((week) => (
                        <text
                            key={week}
                            x={x(week)}
                            y={HEIGHT - 8}
                            textAnchor="middle"
                            className="fill-muted-foreground text-[11px]"
                        >
                            {week === 0 ? 'Start' : `W${week}`}
                        </text>
                    ))}

                <line
                    x1={x(0)}
                    y1={y(startingWeight)}
                    x2={x(lastWeek)}
                    y2={y(goalAt(lastWeek))}
                    className="stroke-muted-foreground"
                    strokeWidth={1.5}
                    strokeDasharray="4 4"
                />
                <text
                    x={x(lastWeek) + 6}
                    y={y(goalAt(lastWeek))}
                    dy="0.32em"
                    className="fill-muted-foreground text-[11px]"
                >
                    Goal pace
                </text>

                {active && (
                    <line
                        x1={x(active.x)}
                        x2={x(active.x)}
                        y1={PAD.top}
                        y2={HEIGHT - PAD.bottom}
                        className="stroke-border"
                        strokeWidth={1}
                    />
                )}

                <path
                    d={path}
                    fill="none"
                    className="stroke-sky-500"
                    strokeWidth={2}
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
                {points.map((p) => (
                    <circle
                        key={p.x}
                        cx={x(p.x)}
                        cy={y(p.y)}
                        r={p.x === hovered ? 5 : 4}
                        className="fill-sky-500 stroke-card"
                        strokeWidth={2}
                    />
                ))}
                {points.length > 0 && (
                    <text
                        x={x(points[points.length - 1].x) + 8}
                        y={y(points[points.length - 1].y)}
                        dy="0.32em"
                        className="fill-foreground text-[11px] font-medium tabular-nums"
                    >
                        {points[points.length - 1].y.toFixed(1)}
                    </text>
                )}
            </svg>

            {active && (
                <div
                    className="pointer-events-none absolute top-2 z-10 -translate-x-1/2 rounded-lg border bg-popover px-3 py-2 text-xs shadow-md"
                    style={{ left: `${(x(active.x) / WIDTH) * 100}%` }}
                >
                    <p className="font-medium">Week {active.week.number}</p>
                    <p className="tabular-nums">
                        <span className="mr-1.5 inline-block size-2 rounded-full bg-sky-500" />
                        {active.y.toFixed(1)} {unit} avg
                    </p>
                    <p className="text-muted-foreground tabular-nums">
                        Goal pace {goalAt(active.x).toFixed(1)} {unit}
                    </p>
                </div>
            )}
        </figure>
    );
}
