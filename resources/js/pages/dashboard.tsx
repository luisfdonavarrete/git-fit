import { Head } from '@inertiajs/react';
import DailySummary from '@/components/daily-summary';
import DiaryDateNav from '@/components/diary-date-nav';
import MealSection from '@/components/meal-section';
import { dashboard } from '@/routes';
import type { DiaryMeal, NutritionGoal, NutritionSummary } from '@/types';

type Props = {
    date: string;
    today: string;
    summary: NutritionSummary;
    goal: NutritionGoal | null;
    meals: DiaryMeal[];
};

export default function Dashboard({
    date,
    today,
    summary,
    goal,
    meals,
}: Props) {
    return (
        <>
            <Head title="Food diary" />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Food diary
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Log what you eat and stay on top of calories and
                            protein.
                        </p>
                    </div>
                    <DiaryDateNav date={date} today={today} />
                </header>

                <DailySummary summary={summary} goal={goal} />

                <div className="grid gap-4 lg:grid-cols-2">
                    {meals.map((meal) => (
                        <MealSection key={meal.type} meal={meal} date={date} />
                    ))}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Food diary',
            href: dashboard(),
        },
    ],
};
