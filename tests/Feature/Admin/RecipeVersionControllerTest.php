<?php

use App\Enums\CalorieRange;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\RecipeVersion;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the create page marks ranges the recipe already has as unavailable', function () {
    $recipe = Recipe::factory()->create();
    RecipeVersion::factory()->for($recipe)->forCalorieRange(CalorieRange::From200)->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.versions.create', [$recipe, 'range' => 500]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/recipes/versions/create')
        ->where('calorieRanges.0', ['value' => 200, 'label' => '200–300', 'available' => false])
        ->where('calorieRanges.1.available', true)
        ->where('selectedRange', 500)
    );
});

test('the create page preselects the first free range when the requested one is taken', function () {
    $recipe = Recipe::factory()->create();
    RecipeVersion::factory()->for($recipe)->forCalorieRange(CalorieRange::From200)->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.versions.create', [$recipe, 'range' => 200]));

    $response->assertInertia(fn (Assert $page) => $page->where('selectedRange', 300));
});

test('a version is stored with its ingredients and steps in submitted order', function () {
    $recipe = Recipe::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.versions.store', $recipe), [
        'calorie_range' => 400,
        'calories' => 450,
        'protein_g' => '32.5',
        'notes' => 'Use thighs for extra juiciness.',
        'ingredients' => [
            ['name' => 'Chicken', 'quantity' => '150', 'unit' => 'g'],
            ['name' => 'Rice', 'quantity' => '0.5', 'unit' => 'cup', 'note' => 'cooked'],
        ],
        'steps' => [
            ['instruction' => 'Season the chicken.'],
            ['instruction' => 'Grill for 6 minutes.'],
        ],
    ]);

    $version = RecipeVersion::sole();
    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.recipes.show', [$recipe, 'range' => 400]));
    expect($version->calorie_range)->toBe(CalorieRange::From400)
        ->and($version->calories)->toBe(450)
        ->and($version->protein_g)->toBe('32.5')
        ->and($version->notes)->toBe('Use thighs for extra juiciness.');
    expect($version->ingredients->map->only('position', 'name', 'note')->all())->toBe([
        ['position' => 1, 'name' => 'Chicken', 'note' => null],
        ['position' => 2, 'name' => 'Rice', 'note' => 'cooked'],
    ]);
    expect($version->steps->pluck('instruction')->all())->toBe(['Season the chicken.', 'Grill for 6 minutes.']);
});

test('a recipe cannot have two versions in the same calorie range', function () {
    $recipe = Recipe::factory()->create();
    RecipeVersion::factory()->for($recipe)->forCalorieRange(CalorieRange::From300)->create();

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.versions.store', $recipe), [
        'calorie_range' => 300,
        'calories' => 350,
        'ingredients' => [],
        'steps' => [],
    ]);

    $response->assertSessionHasErrors(['calorie_range' => 'This recipe already has a version for that calorie range.']);
    expect($recipe->versions()->count())->toBe(1);
});

test('the same calorie range can be used by different recipes', function () {
    RecipeVersion::factory()->forCalorieRange(CalorieRange::From300)->create();
    $recipe = Recipe::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.versions.store', $recipe), [
        'calorie_range' => 300,
        'calories' => 350,
        'ingredients' => [],
        'steps' => [],
    ]);

    $response->assertSessionHasNoErrors();
    expect($recipe->versions()->count())->toBe(1);
});

test('a calorie range outside the supported bands is rejected', function () {
    $recipe = Recipe::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.versions.store', $recipe), [
        'calorie_range' => 250,
        'calories' => 260,
        'ingredients' => [],
        'steps' => [],
    ]);

    $response->assertSessionHasErrors(['calorie_range' => 'The selected calorie range is invalid.']);
    expect(RecipeVersion::count())->toBe(0);
});

test('each ingredient requires a name', function () {
    $recipe = Recipe::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.versions.store', $recipe), [
        'calorie_range' => 300,
        'calories' => 350,
        'ingredients' => [['quantity' => '2', 'unit' => 'tbsp']],
        'steps' => [],
    ]);

    $response->assertSessionHasErrors(['ingredients.0.name' => 'The ingredient name field is required.']);
    expect(RecipeVersion::count())->toBe(0);
});

test('updating a version replaces its ingredients and steps', function () {
    $version = RecipeVersion::factory()->forCalorieRange(CalorieRange::From500)->create();
    $oldIngredient = RecipeIngredient::factory()->for($version, 'version')->create();
    $oldStep = RecipeStep::factory()->for($version, 'version')->create();

    $response = $this->actingAs(User::factory()->create())
        ->put(route('admin.recipes.versions.update', [$version->recipe, $version]), [
            'calorie_range' => 500,
            'calories' => 540,
            'ingredients' => [['name' => 'Salmon']],
            'steps' => [['instruction' => 'Bake at 200°C.']],
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.recipes.show', [$version->recipe, 'range' => 500]));
    $this->assertModelMissing($oldIngredient);
    $this->assertModelMissing($oldStep);
    $version->refresh();
    expect($version->calories)->toBe(540)
        ->and($version->ingredients->pluck('name')->all())->toBe(['Salmon'])
        ->and($version->steps->pluck('instruction')->all())->toBe(['Bake at 200°C.']);
});

test('a version keeps its own calorie range when updated', function () {
    $version = RecipeVersion::factory()->forCalorieRange(CalorieRange::From500)->create();

    $response = $this->actingAs(User::factory()->create())
        ->put(route('admin.recipes.versions.update', [$version->recipe, $version]), [
            'calorie_range' => 500,
            'calories' => 520,
            'ingredients' => [],
            'steps' => [],
        ]);

    $response->assertSessionHasNoErrors();
});

test('a version cannot be reached through a different recipe', function () {
    $version = RecipeVersion::factory()->create();
    $otherRecipe = Recipe::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.versions.edit', [$otherRecipe, $version]));

    $response->assertNotFound();
});

test('a version can be deleted without affecting the recipe', function () {
    $version = RecipeVersion::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->delete(route('admin.recipes.versions.destroy', [$version->recipe, $version]));

    $response->assertRedirect(route('admin.recipes.show', $version->recipe));
    $this->assertModelMissing($version);
    $this->assertModelExists($version->recipe);
});
