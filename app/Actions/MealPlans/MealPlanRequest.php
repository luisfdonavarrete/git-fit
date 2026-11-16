<?php

namespace App\Actions\MealPlans;

use App\Enums\MealType;

/**
 * Everything a planner needs to fill a day: the open slots, the recipes that suit each one, and
 * what's left of the day's goal once already-eaten food is subtracted.
 */
final readonly class MealPlanRequest
{
    /**
     * @param  list<MealType>  $slots  The diary sections to fill, in order; snacks may repeat.
     * @param  array<string, list<array{recipe_id: int, name: string, versions: list<array{id: int, calories: int, protein_g: float}>}>>  $candidates  Recipes keyed by meal type value.
     * @param  array{int, int}  $calorieWindow  The acceptable [min, max] calories for the planned meals.
     */
    public function __construct(
        public array $slots,
        public array $candidates,
        public int $targetCalories,
        public array $calorieWindow,
        public float $targetProtein,
    ) {}

    /**
     * The recipes that suit the given slot.
     *
     * @return list<array{recipe_id: int, name: string, versions: list<array{id: int, calories: int, protein_g: float}>}>
     */
    public function candidatesFor(MealType $slot): array
    {
        return $this->candidates[$slot->value] ?? [];
    }
}
