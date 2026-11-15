<?php

namespace App\Actions\Coach;

use App\Enums\CoachPhase;
use App\Enums\MainGoal;
use App\Enums\Sex;
use App\Enums\TrainingExperience;
use App\Enums\WeightUnit;
use App\Models\CoachProfile;
use Carbon\CarbonInterface;

/**
 * The Pocket Coach model: maintenance calories, weekly weight-change rate and daily macro targets.
 * Mirrors the Built With Science spreadsheet, minus its quirks (see BuildCoachWeeks for the data side).
 *
 * @phpstan-type CoachWeek array{number: int, starts_on: string, ends_on: string, avg_weight_kg: float|null, weigh_in_days: int, avg_calories: float|null, calorie_days: int, measured_tdee: float|null}
 * @phpstan-type CoachTargets array{current_week: int, phase: CoachPhase, weekly_change_pct: float|null, weekly_change_kg: float, latest_weight_kg: float, maintenance: int, maintenance_source: 'formula'|'measured', formula_maintenance: int, calories: int, calorie_range: array{int, int}, protein_g: float, protein_range: array{int, int}, fat_g: float, fat_range: array{int, int}, carbs_g: float, carbs_range: array{int, int}, adapts_from: string}
 */
class CalculateCoachTargets
{
    /**
     * Energy in one kilogram of body weight: the spreadsheet's 3500 kcal per pound, converted once.
     */
    public const float KCAL_PER_KG = 3500 / WeightUnit::KG_PER_LB;

    /**
     * Lean-mass coefficient of the maintenance formula: 9.8 kcal per pound of lean mass, per kilogram.
     */
    public const float KCAL_PER_KG_LEAN_MASS = 9.8 / WeightUnit::KG_PER_LB;

    /**
     * The coaching week from which measured maintenance replaces the formula.
     */
    public const int ADAPTIVE_FROM_WEEK = 4;

    /**
     * @param  list<CoachWeek>  $weeks
     * @return CoachTargets
     */
    public function handle(CoachProfile $profile, array $weeks, CarbonInterface $asOf): array
    {
        $currentWeek = max(1, $profile->weekNumberOn($asOf));
        $completeWeeks = array_values(array_filter($weeks, fn (array $week): bool => $week['number'] < $currentWeek));

        $bodyFat = (float) $profile->body_fat_pct;
        $startingWeightKg = (float) $profile->starting_weight_kg;
        $startingWeightLb = $startingWeightKg / WeightUnit::KG_PER_LB;
        $isMale = $profile->sex === Sex::Male;

        $bracket = $this->bodyFatBracket($profile->sex, $bodyFat);
        $phase = $this->phase($profile->main_goal, $bracket);

        $latestWeightKg = $startingWeightKg;
        foreach ($completeWeeks as $week) {
            $latestWeightKg = $week['avg_weight_kg'] ?? $latestWeightKg;
        }

        $weeklyChangePct = $profile->weekly_change_override_kg === null
            ? $this->weeklyChangePct($phase, $bracket, $profile->training_experience)
            : null;
        $weeklyChangeKg = $weeklyChangePct === null
            ? (float) $profile->weekly_change_override_kg
            : $weeklyChangePct / 100 * $latestWeightKg;

        $formulaMaintenance = (370 + self::KCAL_PER_KG_LEAN_MASS * $startingWeightKg * (1 - $bodyFat / 100)) * ($isMale ? 1.5 : 1.55);
        $measuredMaintenance = $currentWeek >= self::ADAPTIVE_FROM_WEEK ? $this->measuredMaintenance($completeWeeks) : null;
        $maintenance = $measuredMaintenance ?? $formulaMaintenance;

        $calories = max(self::calorieFloor($profile->sex), $maintenance + $weeklyChangeKg * self::KCAL_PER_KG / 7);

        [$proteinPerLb, $proteinMinPerLb, $proteinMaxPerLb] = $this->proteinMultipliers($profile->sex, $bodyFat);
        $protein = $proteinPerLb * $startingWeightLb;

        [$fatShare, $fatMinShare, $fatMaxShare] = $isMale
            ? [$bodyFat < 25 ? 0.22 : 0.25, 0.15, 0.30]
            : [0.30, 0.20, 0.40];
        $fat = $fatShare * $calories / 9;
        $carbs = ($calories - $protein * 4 - $fat * 9) / 4;

        return [
            'current_week' => $currentWeek,
            'phase' => $phase,
            'weekly_change_pct' => $weeklyChangePct,
            'weekly_change_kg' => round($weeklyChangeKg, 3),
            'latest_weight_kg' => round($latestWeightKg, 2),
            'maintenance' => (int) round($maintenance),
            'maintenance_source' => $measuredMaintenance === null ? 'formula' : 'measured',
            'formula_maintenance' => (int) round($formulaMaintenance),
            'calories' => (int) round($calories),
            'calorie_range' => self::calorieRange($calories, $profile->sex),
            'protein_g' => round($protein, 1),
            'protein_range' => [(int) round($proteinMinPerLb * $startingWeightLb), (int) round($proteinMaxPerLb * $startingWeightLb)],
            'fat_g' => round($fat, 1),
            'fat_range' => [self::mround($fatMinShare * $calories / 9, 10), self::mround($fatMaxShare * $calories / 9, 10)],
            'carbs_g' => round($carbs, 1),
            'carbs_range' => [self::mround($carbs * 0.8, 10), self::mround($carbs * 1.2, 10)],
            'adapts_from' => $profile->weekStartsOn(self::ADAPTIVE_FROM_WEEK)->toDateString(),
        ];
    }

    /**
     * The fewest daily calories the coach will ever recommend.
     */
    public static function calorieFloor(Sex $sex): int
    {
        return $sex === Sex::Male ? 1500 : 1200;
    }

    /**
     * The acceptable range shown around a calorie target: ±100 rounded to 50, or target…+100
     * when the low end would fall below the floor.
     *
     * @return array{int, int}
     */
    public static function calorieRange(float $calories, Sex $sex): array
    {
        return $calories - 100 < self::calorieFloor($sex)
            ? [self::mround($calories, 50), self::mround($calories + 100, 50)]
            : [self::mround($calories - 100, 50), self::mround($calories + 100, 50)];
    }

    /**
     * 1 (highest body fat) to 4 (leanest), using the sex-specific thresholds.
     */
    private function bodyFatBracket(Sex $sex, float $bodyFat): int
    {
        [$high, $middle, $low] = $sex === Sex::Male ? [25, 15, 12] : [30, 25, 22];

        return match (true) {
            $bodyFat >= $high => 1,
            $bodyFat > $middle => 2,
            $bodyFat > $low => 3,
            default => 4,
        };
    }

    /**
     * Building muscle from the highest body-fat bracket starts with a cut.
     */
    private function phase(MainGoal $goal, int $bracket): CoachPhase
    {
        return match ($goal) {
            MainGoal::Maintain => CoachPhase::Maintain,
            MainGoal::LoseFat => CoachPhase::Cut,
            MainGoal::BuildMuscle => $bracket === 1 ? CoachPhase::Cut : CoachPhase::LeanBulk,
        };
    }

    /**
     * Recommended weekly change as a percentage of body weight.
     */
    private function weeklyChangePct(CoachPhase $phase, int $bracket, TrainingExperience $experience): float
    {
        return match ($phase) {
            CoachPhase::Maintain => 0.0,
            CoachPhase::Cut => match ($bracket) {
                1, 2 => -0.7,
                3 => -0.5,
                default => -0.3,
            },
            CoachPhase::LeanBulk => $experience === TrainingExperience::Beginner ? 1.5 / 4 : 1 / 4,
        };
    }

    /**
     * Average of the measured weekly TDEEs from week 2 on; week 1 is skipped because it is
     * compared against a single starting weigh-in, which exaggerates the drop with water weight.
     *
     * @param  list<CoachWeek>  $completeWeeks
     */
    private function measuredMaintenance(array $completeWeeks): ?float
    {
        $measured = array_values(array_filter(array_map(
            fn (array $week): ?float => $week['number'] >= 2 ? $week['measured_tdee'] : null,
            $completeWeeks,
        ), fn (?float $tdee): bool => $tdee !== null));

        return $measured === [] ? null : array_sum($measured) / count($measured);
    }

    /**
     * Grams of protein per pound of starting weight: recommended, minimum and maximum.
     *
     * @return array{float, float, float}
     */
    private function proteinMultipliers(Sex $sex, float $bodyFat): array
    {
        if ($sex === Sex::Female) {
            return [$bodyFat <= 25 ? 1.0 : 0.8, 0.8, 1.4];
        }

        return [
            match (true) {
                $bodyFat < 20 => 1.0,
                $bodyFat <= 25 => 0.8,
                default => 0.73,
            },
            $bodyFat < 20 ? 0.8 : 0.73,
            $bodyFat < 20 ? 1.2 : 1.0,
        ];
    }

    /**
     * Round to the nearest multiple, halves away from zero (spreadsheet MROUND).
     */
    private static function mround(float $value, int $multiple): int
    {
        return (int) (round($value / $multiple) * $multiple);
    }
}
