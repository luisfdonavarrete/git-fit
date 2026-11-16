<?php

namespace App\Actions\Coach;

use App\Models\CoachProfile;
use App\Models\DailyCheckIn;
use Carbon\CarbonInterface;

/**
 * Group a user's daily weigh-ins and calories into coaching weeks. Unlike the spreadsheet, a day
 * without data is left out of the averages instead of copying the previous day forward.
 *
 * @phpstan-import-type CoachWeek from CalculateCoachTargets
 */
class BuildCoachWeeks
{
    /**
     * Calorie-logged days a week needs before its measured TDEE is trusted.
     */
    public const int MIN_CALORIE_DAYS = 4;

    /**
     * Every coaching week from the start date up to and including the one containing `$until`.
     *
     * @return list<CoachWeek>
     */
    public function handle(CoachProfile $profile, CarbonInterface $until): array
    {
        $weekCount = $profile->weekNumberOn($until);

        if ($weekCount === 0) {
            return [];
        }

        $from = $profile->started_on->toDateString();
        $to = $profile->weekStartsOn($weekCount)->addDays(6)->toDateString();
        $user = $profile->user;

        $checkIns = $user->dailyCheckIns()
            ->whereDate('checked_on', '>=', $from)
            ->whereDate('checked_on', '<=', $to)
            ->get()
            ->keyBy(fn (DailyCheckIn $checkIn): string => $checkIn->checked_on->toDateString());

        $diaryCalories = $user->mealEntries()
            ->eaten()
            ->toBase()
            ->selectRaw('date(eaten_on) as day, sum(calories) as calories')
            ->whereDate('eaten_on', '>=', $from)
            ->whereDate('eaten_on', '<=', $to)
            ->groupByRaw('date(eaten_on)')
            ->pluck('calories', 'day');

        $weeks = [];
        $previousAverageWeight = null;

        for ($number = 1; $number <= $weekCount; $number++) {
            $startsOn = $profile->weekStartsOn($number);
            $weights = [];
            $calories = [];

            for ($offset = 0; $offset < 7; $offset++) {
                $day = $startsOn->copy()->addDays($offset)->toDateString();
                $checkIn = $checkIns->get($day);

                if ($checkIn?->weight_kg !== null) {
                    $weights[] = (float) $checkIn->weight_kg;
                }

                $dayCalories = $checkIn->calories_override ?? $diaryCalories->get($day);

                if ($dayCalories !== null) {
                    $calories[] = (float) $dayCalories;
                }
            }

            $averageWeight = $weights === [] ? null : array_sum($weights) / count($weights);
            $averageCalories = $calories === [] ? null : array_sum($calories) / count($calories);

            $weeks[] = [
                'number' => $number,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $startsOn->copy()->addDays(6)->toDateString(),
                'avg_weight_kg' => $averageWeight,
                'weigh_in_days' => count($weights),
                'avg_calories' => $averageCalories,
                'calorie_days' => count($calories),
                'measured_tdee' => $number >= 2 && $averageWeight !== null && $previousAverageWeight !== null && $averageCalories !== null && count($calories) >= self::MIN_CALORIE_DAYS
                    ? $averageCalories - ($averageWeight - $previousAverageWeight) * CalculateCoachTargets::KCAL_PER_KG / 7
                    : null,
            ];

            $previousAverageWeight = $averageWeight;
        }

        return $weeks;
    }
}
