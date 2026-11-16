<?php

namespace Database\Factories;

use App\Enums\MealEntryStatus;
use App\Enums\MealType;
use App\Models\MealEntry;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealEntry>
 */
class MealEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'eaten_on' => today(),
            'meal_type' => fake()->randomElement(MealType::cases()),
            'status' => MealEntryStatus::Eaten,
        ];
    }

    /**
     * Snapshot the chosen recipe version (or a fresh one) onto the entry.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (MealEntry $entry): void {
            $entry->fillFromRecipeVersion(
                $entry->recipeVersion ?? RecipeVersion::factory()->create(),
                (float) ($entry->servings ?? 1),
            );
        });
    }

    /**
     * Log the given recipe version, scaled by servings.
     */
    public function fromRecipeVersion(RecipeVersion $version, float $servings = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'recipe_version_id' => $version->id,
            'servings' => $servings,
        ]);
    }

    /**
     * A planned entry the user hasn't confirmed eating yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MealEntryStatus::Pending,
        ]);
    }
}
