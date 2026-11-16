<?php

use App\Enums\GoalSource;
use App\Enums\MealType;
use App\Models\CoachProfile;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\RecipeVersion;
use App\Models\User;

it('logs a recipe version to the chosen section with a snapshot scaled by servings', function () {
    $user = User::factory()->create();
    $version = RecipeVersion::factory()->create(['calories' => 400, 'protein_g' => 30]);

    $response = $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('meal-entries.store'), [
            'recipe_version_id' => $version->id,
            'meal_type' => 'lunch',
            'servings' => 1.5,
            'eaten_on' => '2026-11-14',
        ]);

    $response->assertRedirect(route('dashboard'));
    $entry = $user->mealEntries()->sole();
    expect($entry->meal_type)->toBe(MealType::Lunch)
        ->and($entry->eaten_on->toDateString())->toBe('2026-11-14')
        ->and($entry->calories)->toBe(600)
        ->and($entry->protein_g)->toBe('45.0');
});

it('rejects invalid entries', function (array $overrides, string $field) {
    $version = RecipeVersion::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('meal-entries.store'), [
        'recipe_version_id' => $version->id,
        'meal_type' => 'dinner',
        'servings' => 1,
        'eaten_on' => '2026-11-14',
        ...$overrides,
    ]);

    $response->assertSessionHasErrors($field);
    expect(MealEntry::count())->toBe(0);
})->with([
    'unknown recipe version' => [['recipe_version_id' => 999999], 'recipe_version_id'],
    'recipe tag that is not a diary section' => [['meal_type' => 'high_protein_snack'], 'meal_type'],
    'zero servings' => [['servings' => 0], 'servings'],
    'too many servings' => [['servings' => 21], 'servings'],
    'malformed date' => [['eaten_on' => '14/11/2026'], 'eaten_on'],
]);

it('rescales the entry\'s own snapshot when servings change, ignoring later recipe edits', function () {
    $user = User::factory()->create();
    $version = RecipeVersion::factory()->create(['calories' => 400, 'protein_g' => 30]);
    $entry = MealEntry::factory()->for($user)->fromRecipeVersion($version)->create(['meal_type' => MealType::Lunch]);
    $version->update(['calories' => 900]);

    $this->actingAs($user)->put(route('meal-entries.update', $entry), ['servings' => 2, 'meal_type' => 'dinner']);

    $entry->refresh();
    expect($entry->servings)->toBe('2.00')
        ->and($entry->calories)->toBe(800)
        ->and($entry->protein_g)->toBe('60.0')
        ->and($entry->meal_type)->toBe(MealType::Dinner);
});

it('shows the recipe behind the user\'s entry with ordered ingredients and directions', function () {
    $user = User::factory()->create();
    $version = RecipeVersion::factory()->withNotes()->create(['prep_minutes' => 15]);
    RecipeIngredient::factory()->for($version, 'version')->create(['position' => 2, 'name' => 'Rice']);
    RecipeIngredient::factory()->for($version, 'version')->create(['position' => 1, 'name' => 'Chicken']);
    RecipeStep::factory()->for($version, 'version')->create(['position' => 2, 'instruction' => 'Serve.']);
    RecipeStep::factory()->for($version, 'version')->create(['position' => 1, 'instruction' => 'Cook the rice.']);
    $entry = MealEntry::factory()->for($user)->fromRecipeVersion($version)->create();

    $response = $this->actingAs($user)->getJson(route('meal-entries.show', $entry));

    $response->assertOk()
        ->assertJsonPath('recipe.prep_minutes', 15)
        ->assertJsonPath('recipe.notes', $version->notes)
        ->assertJsonPath('recipe.ingredients.*.name', ['Chicken', 'Rice'])
        ->assertJsonPath('recipe.steps.*.instruction', ['Cook the rice.', 'Serve.']);
});

it('shows no recipe once the entry\'s recipe version is deleted', function () {
    $user = User::factory()->create();
    $version = RecipeVersion::factory()->create();
    $entry = MealEntry::factory()->for($user)->fromRecipeVersion($version)->create();
    $version->delete();

    $this->actingAs($user)->getJson(route('meal-entries.show', $entry))
        ->assertOk()
        ->assertJsonPath('recipe', null);
});

it('removes the user\'s own entry', function () {
    $user = User::factory()->create();
    $entry = MealEntry::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('meal-entries.destroy', $entry))->assertRedirect();

    $this->assertModelMissing($entry);
});

it('forbids changing another user\'s entry', function (string $method, string $routeName) {
    $entry = MealEntry::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->{$method}(route($routeName, $entry), ['servings' => 2]);

    $response->assertForbidden();
    $this->assertModelExists($entry);
})->with([
    'view' => ['getJson', 'meal-entries.show'],
    'update' => ['put', 'meal-entries.update'],
    'delete' => ['delete', 'meal-entries.destroy'],
]);

it('redirects guests to login', function () {
    $this->post(route('meal-entries.store'))->assertRedirect(route('login'));
});

it('recalculates the coach goal when the diary changes', function () {
    $this->travelTo('2026-11-14 09:00');
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create(['started_on' => '2026-11-09']);
    $version = RecipeVersion::factory()->create();

    $this->actingAs($user)->post(route('meal-entries.store'), [
        'recipe_version_id' => $version->id,
        'meal_type' => 'lunch',
        'servings' => 1,
        'eaten_on' => '2026-11-14',
    ]);

    expect($user->goalOn(today())?->source)->toBe(GoalSource::Coach);
});
