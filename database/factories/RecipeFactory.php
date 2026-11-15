<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(rtrim(fake()->unique()->sentence(3, false), '.'));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(12),
            'image_path' => null,
        ];
    }

    /**
     * Tag the recipe with the given meal types.
     */
    public function withMealTypes(MealType ...$mealTypes): static
    {
        return $this->afterCreating(fn (Recipe $recipe) => $recipe->syncMealTypes($mealTypes));
    }
}
