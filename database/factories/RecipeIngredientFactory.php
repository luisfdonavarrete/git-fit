<?php

namespace Database\Factories;

use App\Models\RecipeIngredient;
use App\Models\RecipeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
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
            'position' => fake()->numberBetween(1, 20),
            'name' => fake()->randomElement(['chicken breast', 'brown rice', 'olive oil', 'garlic', 'spinach', 'greek yogurt', 'oats', 'egg']),
            'quantity' => fake()->randomFloat(2, 0.25, 500),
            'unit' => fake()->randomElement(['g', 'ml', 'tbsp', 'tsp', 'cup', null]),
            'note' => null,
        ];
    }
}
