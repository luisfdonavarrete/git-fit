<?php

use App\Enums\MealType;
use App\Models\MealEntry;
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
    'update' => ['put', 'meal-entries.update'],
    'delete' => ['delete', 'meal-entries.destroy'],
]);

it('redirects guests to login', function () {
    $this->post(route('meal-entries.store'))->assertRedirect(route('login'));
});
