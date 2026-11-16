<?php

namespace App\Actions\MealPlans;

use App\Enums\MealType;
use Random\Randomizer;

/**
 * Plan a day from the recipe library with a randomized search: sample many combinations, nudge
 * each one's calorie versions toward the target, and keep the best. Running it again gives a
 * different plan.
 */
class PlanDayFromRecipes implements MealPlanner
{
    /**
     * How much of the day's calories each meal aims for before renormalizing over open slots.
     *
     * @var array<string, float>
     */
    private const array CALORIE_SHARES = [
        'breakfast' => 0.25,
        'lunch' => 0.30,
        'dinner' => 0.30,
        'snack' => 0.075,
    ];

    private const int ATTEMPTS = 400;

    private Randomizer $randomizer;

    public function __construct(?Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer ?? new Randomizer;
    }

    public function plan(MealPlanRequest $request): array
    {
        $slotTargets = $this->slotTargets($request);
        $best = null;
        $bestScore = INF;

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $combination = $this->randomCombination($request, $slotTargets);

            if ($combination === null) {
                continue;
            }

            $combination = $this->improve($request, $combination);
            $score = $this->score($request, $combination);

            if ($score < $bestScore) {
                $best = $combination;
                $bestScore = $score;
            }
        }

        if ($best === null) {
            throw new MealPlanFailed(__('There aren\'t enough recipes to plan this day.'));
        }

        return array_map(fn (array $pick): int => $pick['version']['id'], $best);
    }

    /**
     * Calories each open slot should aim for.
     *
     * @return list<float>
     */
    private function slotTargets(MealPlanRequest $request): array
    {
        $shares = array_map(fn (MealType $slot): float => self::CALORIE_SHARES[$slot->value] ?? 0.1, $request->slots);
        $total = max(0.001, array_sum($shares));

        return array_map(fn (float $share): float => $request->targetCalories * $share / $total, $shares);
    }

    /**
     * A random recipe per slot (never the same recipe twice), each at the version nearest its slot target.
     *
     * @param  list<float>  $slotTargets
     * @return list<array{recipe: array{recipe_id: int, name: string, versions: list<array{id: int, calories: int, protein_g: float}>}, version: array{id: int, calories: int, protein_g: float}}>|null
     */
    private function randomCombination(MealPlanRequest $request, array $slotTargets): ?array
    {
        $combination = [];
        $usedRecipeIds = [];

        foreach ($request->slots as $index => $slot) {
            $recipes = array_values(array_filter(
                $request->candidatesFor($slot),
                fn (array $recipe): bool => $recipe['versions'] !== [] && ! in_array($recipe['recipe_id'], $usedRecipeIds, true),
            ));

            if ($recipes === []) {
                return null;
            }

            $recipe = $recipes[$this->randomizer->getInt(0, count($recipes) - 1)];
            $usedRecipeIds[] = $recipe['recipe_id'];
            $combination[] = ['recipe' => $recipe, 'version' => $this->nearestVersion($recipe['versions'], $slotTargets[$index])];
        }

        return $combination;
    }

    /**
     * @param  non-empty-list<array{id: int, calories: int, protein_g: float}>  $versions
     * @return array{id: int, calories: int, protein_g: float}
     */
    private function nearestVersion(array $versions, float $calories): array
    {
        usort($versions, fn (array $a, array $b): int => abs($a['calories'] - $calories) <=> abs($b['calories'] - $calories));

        return $versions[0];
    }

    /**
     * Hill-climb: keep swapping a slot to another version of its recipe while that lowers the score.
     *
     * @param  list<array{recipe: array{recipe_id: int, name: string, versions: list<array{id: int, calories: int, protein_g: float}>}, version: array{id: int, calories: int, protein_g: float}}>  $combination
     * @return list<array{recipe: array{recipe_id: int, name: string, versions: list<array{id: int, calories: int, protein_g: float}>}, version: array{id: int, calories: int, protein_g: float}}>
     */
    private function improve(MealPlanRequest $request, array $combination): array
    {
        $score = $this->score($request, $combination);

        for ($pass = 0; $pass < 3; $pass++) {
            $improved = false;

            foreach ($combination as $index => $pick) {
                foreach ($pick['recipe']['versions'] as $version) {
                    $candidate = $combination;
                    $candidate[$index] = ['recipe' => $pick['recipe'], 'version' => $version];
                    $candidateScore = $this->score($request, $candidate);

                    if ($candidateScore < $score) {
                        $combination = $candidate;
                        $score = $candidateScore;
                        $improved = true;
                    }
                }
            }

            if (! $improved) {
                break;
            }
        }

        return $combination;
    }

    /**
     * Lower is better: calories outside the window dominate, then distance from the calorie
     * target and any protein shortfall, both relative to their targets.
     *
     * @param  list<array{recipe: array{recipe_id: int, name: string, versions: list<array{id: int, calories: int, protein_g: float}>}, version: array{id: int, calories: int, protein_g: float}}>  $combination
     */
    private function score(MealPlanRequest $request, array $combination): float
    {
        $calories = array_sum(array_map(fn (array $pick): int => $pick['version']['calories'], $combination));
        $protein = array_sum(array_map(fn (array $pick): float => $pick['version']['protein_g'], $combination));
        [$minCalories, $maxCalories] = $request->calorieWindow;

        $outsideWindow = max(0, $minCalories - $calories, $calories - $maxCalories);
        $calorieError = abs($calories - $request->targetCalories) / max(1, $request->targetCalories);
        $proteinShortfall = max(0.0, $request->targetProtein - $protein) / max(1.0, $request->targetProtein);

        return $outsideWindow + $calorieError + $proteinShortfall;
    }
}
