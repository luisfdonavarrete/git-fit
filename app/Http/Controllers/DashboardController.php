<?php

namespace App\Http\Controllers;

use App\Actions\Coach\BuildCoachWeeks;
use App\Actions\Coach\CalculateCoachTargets;
use App\Actions\Coach\PresentCoachTargets;
use App\Actions\Coach\SyncCoachGoal;
use App\Enums\GoalSource;
use App\Enums\MealEntryStatus;
use App\Enums\MealPlannerType;
use App\Enums\MealType;
use App\Enums\WeightUnit;
use App\Models\DailyCheckIn;
use App\Models\MealEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the food diary for one day (today unless `?date=YYYY-MM-DD` is given).
     */
    public function show(
        Request $request,
        SyncCoachGoal $syncCoachGoal,
        BuildCoachWeeks $buildCoachWeeks,
        CalculateCoachTargets $calculateCoachTargets,
        PresentCoachTargets $presentCoachTargets,
    ): Response {
        $user = $request->user();
        $today = $user->localToday();
        $date = $this->requestedDate($request, $today);
        $profile = $user->coachProfile;
        $unit = $profile->weight_unit ?? WeightUnit::Lb;
        $coach = null;

        if ($profile !== null) {
            $asOf = $today->max($profile->started_on);
            $targets = $calculateCoachTargets->handle($profile, $buildCoachWeeks->handle($profile, $asOf), $asOf);
            $weekStartsOn = $profile->weekStartsOn($targets['current_week'])->toDateString();

            $hasCurrentGoal = $user->nutritionGoals()
                ->whereDate('effective_from', $weekStartsOn)
                ->where('source', GoalSource::Coach)
                ->exists();

            if (! $hasCurrentGoal) {
                $syncCoachGoal->handle($user);
            }

            $dayGoal = $user->goalOn($date);
            $coach = [
                ...$presentCoachTargets->handle($targets, $unit),
                'day_calorie_range' => $dayGoal === null ? null : CalculateCoachTargets::calorieRange($dayGoal->calories, $profile->sex),
            ];
        }

        $entries = $user->mealEntries()
            ->onDate($date)
            ->with('recipeVersion.recipe:id,image_path')
            ->oldest()
            ->get();
        $pending = $entries->where('status', MealEntryStatus::Pending);
        $entriesByMeal = $entries->groupBy(fn (MealEntry $entry): string => $entry->meal_type->value);

        return Inertia::render('dashboard', [
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'summary' => $user->nutritionSummaryFor($date),
            'goal' => $user->goalOn($today)?->only('calories', 'protein_g'),
            'coach' => $coach,
            'checkIn' => $this->checkIn($user, $date, $unit),
            'mealPlan' => [
                'pending_count' => $pending->count(),
                'planned' => [
                    'calories' => (int) $pending->sum('calories'),
                    'protein_g' => round((float) $pending->sum(fn (MealEntry $entry): float => (float) $entry->protein_g), 1),
                ],
                'planners' => MealPlannerType::options(),
            ],
            'meals' => array_map(fn (MealType $type): array => [
                'type' => $type->value,
                'label' => $type === MealType::Snack ? __('Snacks') : $type->label(),
                'entries' => $entriesByMeal->get($type->value, collect())->map(fn (MealEntry $entry): array => [
                    'id' => $entry->id,
                    'name' => $entry->name,
                    'status' => $entry->status->value,
                    'servings' => $entry->servings,
                    'calorie_range' => $entry->calorie_range->value,
                    'calories' => $entry->calories,
                    'protein_g' => $entry->protein_g,
                    'carbs_g' => $entry->carbs_g,
                    'fat_g' => $entry->fat_g,
                    'image_url' => $entry->recipeVersion?->recipe->image_url,
                ])->values()->all(),
            ], MealType::diarySections()),
        ]);
    }

    /**
     * The day's weigh-in and calorie override, plus 7-day average weights for the trend.
     *
     * @return array{unit: string, weight: float|null, calories_override: int|null, average_weight: float|null, previous_average_weight: float|null}
     */
    private function checkIn(User $user, CarbonInterface $date, WeightUnit $unit): array
    {
        $checkIns = $user->dailyCheckIns()
            ->whereDate('checked_on', '>=', $date->copy()->subDays(13)->toDateString())
            ->whereDate('checked_on', '<=', $date->toDateString())
            ->get();

        $average = function (int $fromDaysAgo, int $toDaysAgo) use ($checkIns, $date, $unit): ?float {
            $weights = $checkIns
                ->filter(fn (DailyCheckIn $checkIn): bool => $checkIn->weight_kg !== null
                    && $checkIn->checked_on->between($date->copy()->subDays($fromDaysAgo), $date->copy()->subDays($toDaysAgo)))
                ->map(fn (DailyCheckIn $checkIn): float => (float) $checkIn->weight_kg);

            return $weights->isEmpty() ? null : round($unit->fromKg($weights->avg()), 1);
        };

        $today = $checkIns->first(fn (DailyCheckIn $checkIn): bool => $checkIn->checked_on->isSameDay($date));

        return [
            'unit' => $unit->label(),
            'weight' => $today?->weight_kg === null ? null : round($unit->fromKg((float) $today->weight_kg), 1),
            'calories_override' => $today?->calories_override,
            'average_weight' => $average(6, 0),
            'previous_average_weight' => $average(13, 7),
        ];
    }

    /**
     * The diary day from the query string, falling back to today when missing or malformed.
     */
    private function requestedDate(Request $request, CarbonInterface $today): CarbonInterface
    {
        $value = $request->string('date')->value();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return $today;
        }

        return Date::parse($value)->startOfDay();
    }
}
