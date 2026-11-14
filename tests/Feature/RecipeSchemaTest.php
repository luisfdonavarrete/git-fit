<?php

use App\Enums\CalorieRange;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\RecipeVersion;
use Illuminate\Database\UniqueConstraintViolationException;

it('returns a recipe\'s versions ordered by calorie range', function () {
    $recipe = Recipe::factory()->create();
    RecipeVersion::factory()->for($recipe)->forCalorieRange(CalorieRange::Over800)->create();
    RecipeVersion::factory()->for($recipe)->forCalorieRange(CalorieRange::From300)->create();

    $ranges = $recipe->versions()->pluck('calorie_range');

    expect($ranges->all())->toBe([CalorieRange::From300, CalorieRange::Over800]);
});

it('returns steps and ingredients in position order', function () {
    $version = RecipeVersion::factory()->create();
    RecipeStep::factory()->for($version, 'version')->sequence(
        ['position' => 2, 'instruction' => 'Bake'],
        ['position' => 1, 'instruction' => 'Mix'],
    )->count(2)->create();
    RecipeIngredient::factory()->for($version, 'version')->sequence(
        ['position' => 2, 'name' => 'salt'],
        ['position' => 1, 'name' => 'flour'],
    )->count(2)->create();

    $version->refresh();

    expect($version->steps->pluck('instruction')->all())->toBe(['Mix', 'Bake'])
        ->and($version->ingredients->pluck('name')->all())->toBe(['flour', 'salt']);
});

it('rejects a second version with the same calorie range on one recipe', function () {
    $recipe = Recipe::factory()->create();
    RecipeVersion::factory()->for($recipe)->forCalorieRange(CalorieRange::From400)->create();

    RecipeVersion::factory()->for($recipe)->forCalorieRange(CalorieRange::From400)->create();
})->throws(UniqueConstraintViolationException::class);

it('deletes versions, steps, and ingredients when the recipe is deleted', function () {
    $version = RecipeVersion::factory()->create();
    $step = RecipeStep::factory()->for($version, 'version')->create();
    $ingredient = RecipeIngredient::factory()->for($version, 'version')->create();

    $version->recipe->delete();

    $this->assertModelMissing($version);
    $this->assertModelMissing($step);
    $this->assertModelMissing($ingredient);
});

it('labels each calorie range', function (CalorieRange $calorieRange, string $label) {
    expect($calorieRange->label())->toBe($label);
})->with([
    'lowest band' => [CalorieRange::From200, '200–300'],
    'middle band' => [CalorieRange::From500, '500–600'],
    'open-ended top band' => [CalorieRange::Over800, '800+'],
]);
