<?php

namespace App\Http\Controllers;

use App\Actions\Coach\BuildCoachWeeks;
use App\Actions\Coach\CalculateCoachTargets;
use App\Actions\Coach\PresentCoachTargets;
use App\Models\NutritionGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    /**
     * Week-by-week averages, measured maintenance and monthly actual vs goal weight change.
     */
    public function show(
        Request $request,
        BuildCoachWeeks $buildCoachWeeks,
        CalculateCoachTargets $calculateCoachTargets,
        PresentCoachTargets $presentCoachTargets,
    ): Response|RedirectResponse {
        $user = $request->user();
        $profile = $user->coachProfile;

        if ($profile === null) {
            return to_route('coach.edit');
        }

        $unit = $profile->weight_unit;
        $asOf = today()->max($profile->started_on);
        $weeks = $buildCoachWeeks->handle($profile, $asOf);
        $targets = $calculateCoachTargets->handle($profile, $weeks, $asOf);
        $goals = $user->nutritionGoals()->get();

        $previousWeightKg = (float) $profile->starting_weight_kg;
        $rows = [];

        foreach ($weeks as $week) {
            $changeKg = $week['avg_weight_kg'] === null ? null : $week['avg_weight_kg'] - $previousWeightKg;
            $goal = $goals->first(fn (NutritionGoal $goal): bool => $goal->effective_from->toDateString() <= $week['starts_on']);

            $rows[] = [
                'number' => $week['number'],
                'starts_on' => $week['starts_on'],
                'ends_on' => $week['ends_on'],
                'is_current' => $week['number'] === $targets['current_week'],
                'avg_weight' => $week['avg_weight_kg'] === null ? null : round($unit->fromKg($week['avg_weight_kg']), 2),
                'weigh_in_days' => $week['weigh_in_days'],
                'change' => $changeKg === null ? null : round($unit->fromKg($changeKg), 2),
                'avg_calories' => $week['avg_calories'] === null ? null : (int) round($week['avg_calories']),
                'calorie_days' => $week['calorie_days'],
                'measured_tdee' => $week['measured_tdee'] === null ? null : (int) round($week['measured_tdee']),
                'target_calories' => $goal?->calories,
            ];

            $previousWeightKg = $week['avg_weight_kg'] ?? $previousWeightKg;
        }

        return Inertia::render('progress', [
            'startedOn' => $profile->started_on->toDateString(),
            'today' => today()->toDateString(),
            'unit' => $unit->label(),
            'startingWeight' => round($unit->fromKg((float) $profile->starting_weight_kg), 1),
            'targets' => $presentCoachTargets->handle($targets, $unit),
            'weeks' => $rows,
            'months' => $this->months($rows, round($unit->fromKg($targets['weekly_change_kg']), 2)),
        ]);
    }

    /**
     * Sum each month's weekly changes and compare with the goal pace for the same number of weeks.
     *
     * @param  list<array{starts_on: string, change: float|null}>  $rows
     * @return list<array{month: string, label: string, weeks: int, actual_change: float, goal_change: float}>
     */
    private function months(array $rows, float $weeklyGoalChange): array
    {
        $months = [];

        foreach ($rows as $row) {
            $month = substr($row['starts_on'], 0, 7);
            $months[$month] ??= [
                'month' => $month,
                'label' => Date::createFromFormat('!Y-m', $month)?->format('M Y') ?? $month,
                'weeks' => 0,
                'actual_change' => 0.0,
                'goal_change' => 0.0,
            ];
            $months[$month]['weeks']++;
            $months[$month]['actual_change'] = round($months[$month]['actual_change'] + ($row['change'] ?? 0), 2);
            $months[$month]['goal_change'] = round($months[$month]['weeks'] * $weeklyGoalChange, 2);
        }

        return array_values($months);
    }
}
