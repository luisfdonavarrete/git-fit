import { router } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dayLabel, shiftDate } from '@/lib/nutrition';
import { dashboard } from '@/routes';

function visit(date: string, today: string) {
    router.get(
        dashboard.url(date === today ? undefined : { query: { date } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

/**
 * Previous / next day controls with a "jump to today" shortcut.
 */
export default function DiaryDateNav({
    date,
    today,
}: {
    date: string;
    today: string;
}) {
    return (
        <div className="flex items-center gap-2">
            <div className="flex items-center rounded-full border bg-card p-1 shadow-xs">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 rounded-full"
                    aria-label="Previous day"
                    onClick={() => visit(shiftDate(date, -1), today)}
                >
                    <ChevronLeft />
                </Button>
                <span className="min-w-28 px-2 text-center text-sm font-medium">
                    {dayLabel(date, today)}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 rounded-full"
                    aria-label="Next day"
                    onClick={() => visit(shiftDate(date, 1), today)}
                >
                    <ChevronRight />
                </Button>
            </div>
            {date !== today && (
                <Button
                    variant="outline"
                    size="sm"
                    className="rounded-full"
                    onClick={() => visit(today, today)}
                >
                    <CalendarDays />
                    Today
                </Button>
            )}
        </div>
    );
}
