<?php

use App\Enums\MealType;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;

it('suggests recipes tagged for the section, including high-protein snacks for snacks', function () {
    $snack = RecipeVersion::factory()->for(Recipe::factory()->withMealTypes(MealType::Snack))->create()->recipe;
    $proteinSnack = RecipeVersion::factory()->for(Recipe::factory()->withMealTypes(MealType::HighProteinSnack))->create()->recipe;
    RecipeVersion::factory()->for(Recipe::factory()->withMealTypes(MealType::Dinner))->create();

    $response = $this->actingAs(User::factory()->create())->getJson(route('recipes.search', ['meal_type' => 'snack']));

    expect(collect($response->json('recipes'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$snack->id, $proteinSnack->id])->sort()->values()->all());
});

it('lists every recipe with versions when all is requested, filtered by name', function () {
    RecipeVersion::factory()->for(Recipe::factory()->withMealTypes(MealType::Dinner)->state(['name' => 'Salmon Bowl']))->create();
    RecipeVersion::factory()->for(Recipe::factory()->state(['name' => 'Chicken Wrap']))->create();
    Recipe::factory()->state(['name' => 'Salmon Draft'])->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('recipes.search', ['meal_type' => 'breakfast', 'all' => 1, 'search' => 'salmon']));

    expect($response->json('recipes.*.name'))->toBe(['Salmon Bowl'])
        ->and($response->json('recipes.0.versions'))->toHaveCount(1);
});
