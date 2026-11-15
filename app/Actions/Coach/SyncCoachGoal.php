<?php

namespace App\Actions\Coach;

use App\Enums\GoalSource;
use App\Models\NutritionGoal;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Recalculate the coach's targets and save them as the goal for the current coaching week.
 * Earlier weeks keep the goals they had.
 */
class SyncCoachGoal
{
    public function __construct(
        private BuildCoachWeeks $buildCoachWeeks,
        private CalculateCoachTargets $calculateCoachTargets,
    ) {}

    public function handle(User $user, ?CarbonInterface $today = null): ?NutritionGoal
    {
        $profile = $user->coachProfile;

        if ($profile === null) {
            return null;
        }

        $asOf = ($today ?? today())->max($profile->started_on);
        $targets = $this->calculateCoachTargets->handle($profile, $this->buildCoachWeeks->handle($profile, $asOf), $asOf);

        return $user->nutritionGoals()->updateOrCreate(
            ['effective_from' => $profile->weekStartsOn($targets['current_week'])],
            [
                'source' => GoalSource::Coach,
                'maintenance_calories' => $targets['maintenance'],
                'calories' => $targets['calories'],
                'protein_g' => $targets['protein_g'],
                'fat_g' => $targets['fat_g'],
                'carbs_g' => $targets['carbs_g'],
                'fiber_g' => null,
                'sugar_g' => null,
                'sodium_mg' => null,
            ],
        );
    }
}
