<?php

namespace Database\Factories;

use App\Models\NutritionGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionGoal>
 */
class NutritionGoalFactory extends Factory
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
            'effective_from' => today(),
            'calories' => fake()->numberBetween(18, 26) * 100,
            'protein_g' => fake()->numberBetween(120, 180),
            'carbs_g' => null,
            'fat_g' => null,
            'fiber_g' => null,
            'sugar_g' => null,
            'sodium_mg' => null,
        ];
    }

    /**
     * Also set targets for the optional macros.
     */
    public function withAllMacros(): static
    {
        return $this->state(fn (array $attributes) => [
            'carbs_g' => fake()->numberBetween(150, 300),
            'fat_g' => fake()->numberBetween(50, 90),
            'fiber_g' => fake()->numberBetween(25, 40),
            'sugar_g' => fake()->numberBetween(30, 60),
            'sodium_mg' => fake()->numberBetween(1500, 2300),
        ]);
    }
}
