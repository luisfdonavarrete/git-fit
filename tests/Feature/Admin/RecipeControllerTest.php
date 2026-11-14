<?php

use App\Enums\MealType;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.recipes.index'));

    $response->assertRedirect(route('login'));
});

test('unverified users are redirected to the verification notice', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('admin.recipes.index'));

    $response->assertRedirect(route('verification.notice'));
});

test('recipes are listed with their versions', function () {
    $recipe = Recipe::factory()->create();
    RecipeVersion::factory()->for($recipe)->create();

    $response = $this->actingAs(User::factory()->create())->get(route('admin.recipes.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/recipes/index')
        ->has('recipes.data', 1)
        ->has('recipes.data.0.versions', 1)
        ->has('calorieRanges', 8)
    );
});

test('recipes can be filtered by name', function () {
    Recipe::factory()->create(['name' => 'Chicken Bowl']);
    Recipe::factory()->create(['name' => 'Berry Oats']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.index', ['search' => 'chick']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Chicken Bowl')
        ->where('filters.search', 'chick')
    );
});

test('a recipe can be created with a slug generated from its name', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Lemon Herb Chicken',
        'description' => 'Bright and quick.',
    ]);

    $recipe = Recipe::sole();
    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.recipes.show', $recipe));
    expect($recipe->slug)->toBe('lemon-herb-chicken')
        ->and($recipe->description)->toBe('Bright and quick.');
});

test('a recipe slug must be unique', function () {
    Recipe::factory()->create(['slug' => 'chicken-bowl']);

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Chicken Bowl',
    ]);

    $response->assertSessionHasErrors(['slug' => 'The slug has already been taken.']);
    expect(Recipe::count())->toBe(1);
});

test('a recipe can keep its own slug when updated', function () {
    $recipe = Recipe::factory()->create(['slug' => 'chicken-bowl']);

    $response = $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => 'Renamed Bowl',
        'slug' => 'chicken-bowl',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.recipes.show', $recipe));
    expect($recipe->refresh()->name)->toBe('Renamed Bowl');
});

test('a recipe requires a name', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), []);

    $response->assertSessionHasErrors(['name' => 'The name field is required.']);
    expect(Recipe::count())->toBe(0);
});

test('the recipe page shows every version with ingredients and steps', function () {
    $version = RecipeVersion::factory()->create();

    $response = $this->actingAs(User::factory()->create())->get(route('admin.recipes.show', $version->recipe));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/recipes/show')
        ->where('recipe.versions.0.id', $version->id)
        ->has('recipe.versions.0.ingredients')
        ->has('recipe.versions.0.steps')
    );
});

test('a recipe can be deleted', function () {
    $recipe = Recipe::factory()->create();

    $response = $this->actingAs(User::factory()->create())->delete(route('admin.recipes.destroy', $recipe));

    $response->assertRedirect(route('admin.recipes.index'));
    $this->assertModelMissing($recipe);
});

test('recipes can be filtered by meal type', function () {
    Recipe::factory()->withMealTypes(MealType::Breakfast)->create(['name' => 'Berry Oats']);
    Recipe::factory()->withMealTypes(MealType::Dinner)->create(['name' => 'Salmon Tray Bake']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.index', ['meal_type' => 'breakfast']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Berry Oats')
        ->where('recipes.data.0.meal_types.0.meal_type', 'breakfast')
        ->where('filters.meal_type', 'breakfast')
    );
});

test('a recipe can be created with several meal types', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Turkey Wrap',
        'meal_types' => ['lunch', 'high_protein_snack'],
    ]);

    $response->assertSessionHasNoErrors();
    expect(Recipe::sole()->mealTypes->pluck('meal_type')->all())
        ->toEqualCanonicalizing([MealType::Lunch, MealType::HighProteinSnack]);
});

test('updating a recipe replaces its meal types', function () {
    $recipe = Recipe::factory()->withMealTypes(MealType::Breakfast, MealType::Snack)->create();

    $response = $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => $recipe->name,
        'meal_types' => ['snack', 'dinner'],
    ]);

    $response->assertSessionHasNoErrors();
    expect($recipe->mealTypes()->pluck('meal_type')->all())
        ->toEqualCanonicalizing([MealType::Snack, MealType::Dinner]);
});

test('updating a recipe without meal types clears them', function () {
    $recipe = Recipe::factory()->withMealTypes(MealType::Lunch)->create();

    $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => $recipe->name,
    ]);

    expect($recipe->mealTypes()->count())->toBe(0);
});

test('an unknown meal type is rejected', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Mystery Dish',
        'meal_types' => ['brunch'],
    ]);

    $response->assertSessionHasErrors(['meal_types.0' => 'The selected meal type is invalid.']);
    expect(Recipe::count())->toBe(0);
});
