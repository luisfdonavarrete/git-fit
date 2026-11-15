<?php

namespace App\Actions\Coach;

use App\Enums\WeightUnit;

/**
 * Shape calculated targets for the frontend, with weights in the user's unit.
 *
 * @phpstan-import-type CoachTargets from CalculateCoachTargets
 */
class PresentCoachTargets
{
    /**
     * @param  CoachTargets  $targets
     * @return array<string, mixed>
     */
    public function handle(array $targets, WeightUnit $unit): array
    {
        return [
            'current_week' => $targets['current_week'],
            'phase' => ['value' => $targets['phase']->value, 'label' => $targets['phase']->label()],
            'unit' => $unit->label(),
            'weekly_change' => round($unit->fromKg($targets['weekly_change_kg']), 2),
            'weekly_change_pct' => $targets['weekly_change_pct'],
            'latest_weight' => round($unit->fromKg($targets['latest_weight_kg']), 1),
            'maintenance' => $targets['maintenance'],
            'maintenance_source' => $targets['maintenance_source'],
            'formula_maintenance' => $targets['formula_maintenance'],
            'daily_difference' => $targets['calories'] - $targets['maintenance'],
            'calories' => $targets['calories'],
            'calorie_range' => $targets['calorie_range'],
            'protein_g' => $targets['protein_g'],
            'protein_range' => $targets['protein_range'],
            'fat_g' => $targets['fat_g'],
            'fat_range' => $targets['fat_range'],
            'carbs_g' => $targets['carbs_g'],
            'carbs_range' => $targets['carbs_range'],
            'adapts_from' => $targets['adapts_from'],
            'is_adaptive' => $targets['current_week'] >= CalculateCoachTargets::ADAPTIVE_FROM_WEEK,
        ];
    }
}
