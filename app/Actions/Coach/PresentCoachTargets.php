<?php

namespace App\Actions\Coach;

use App\Enums\WeightUnit;

/**
 * Shape calculated targets for the frontend, with weights in the user's unit.
 *
 * @phpstan-import-type CoachTargets from CalculateCoachTargets
 * @phpstan-import-type CoachBreakdown from CalculateCoachTargets
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
            'breakdown' => $this->breakdown($targets['breakdown'], $unit),
        ];
    }

    /**
     * The inputs behind each target, with every per-weight constant expressed per unit of the user's weight.
     *
     * @param  CoachBreakdown  $breakdown
     * @return array<string, mixed>
     */
    private function breakdown(array $breakdown, WeightUnit $unit): array
    {
        $kgPerUnit = $unit->toKg(1.0);
        $lbPerUnit = $kgPerUnit / WeightUnit::KG_PER_LB;

        return [
            'starting_weight' => round($unit->fromKg($breakdown['starting_weight_kg']), 1),
            'body_fat_pct' => $breakdown['body_fat_pct'],
            'lean_mass' => round($unit->fromKg($breakdown['lean_mass_kg']), 1),
            'lean_mass_kcal' => round(CalculateCoachTargets::KCAL_PER_KG_LEAN_MASS * $kgPerUnit, 1),
            'kcal_per_unit' => (int) round(CalculateCoachTargets::KCAL_PER_KG * $kgPerUnit),
            'activity_multiplier' => $breakdown['activity_multiplier'],
            'rate_rule' => $breakdown['rate_rule'],
            'unfloored_calories' => (int) round($breakdown['unfloored_calories']),
            'calorie_floor' => $breakdown['calorie_floor'],
            'is_floored' => $breakdown['is_floored'],
            'protein_per_unit' => array_map(fn (float $perLb): float => round($perLb * $lbPerUnit, 2), $breakdown['protein_per_lb']),
            'fat_share' => $breakdown['fat_share'],
            'measured_weeks' => array_map(fn (array $week): array => [
                'number' => $week['number'],
                'avg_calories' => $week['avg_calories'] === null ? null : (int) round($week['avg_calories']),
                'weight_change' => $week['weight_change_kg'] === null ? null : round($unit->fromKg($week['weight_change_kg']), 2),
                'tdee' => (int) round($week['tdee']),
            ], $breakdown['measured_weeks']),
        ];
    }
}
