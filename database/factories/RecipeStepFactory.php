<?php

namespace Database\Factories;

use App\Models\RecipeStep;
use App\Models\RecipeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeStep>
 */
class RecipeStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_version_id' => RecipeVersion::factory(),
            'position' => fake()->unique()->numberBetween(1, 1000),
            'instruction' => fake()->sentence(10),
        ];
    }
}
