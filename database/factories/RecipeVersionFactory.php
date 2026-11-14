<?php

namespace Database\Factories;

use App\Enums\CalorieRange;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeVersion>
 */
class RecipeVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $calorieRange = fake()->randomElement(CalorieRange::cases());

        return [
            'recipe_id' => Recipe::factory(),
            'calorie_range' => $calorieRange,
            'calories' => fake()->numberBetween($calorieRange->value, $calorieRange->value + 99),
            'protein_g' => fake()->randomFloat(1, 5, 60),
            'carbs_g' => fake()->randomFloat(1, 5, 100),
            'fat_g' => fake()->randomFloat(1, 2, 40),
            'fiber_g' => fake()->randomFloat(1, 0, 15),
            'sugar_g' => fake()->randomFloat(1, 0, 30),
            'sodium_mg' => fake()->numberBetween(50, 1500),
            'notes' => null,
        ];
    }

    /**
     * Target a specific calorie band.
     */
    public function forCalorieRange(CalorieRange $calorieRange): static
    {
        return $this->state(fn (array $attributes) => [
            'calorie_range' => $calorieRange,
            'calories' => fake()->numberBetween($calorieRange->value, $calorieRange->value + 99),
        ]);
    }

    /**
     * Include an extra note for this version.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => fake()->sentence(),
        ]);
    }
}
