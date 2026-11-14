<?php

use App\Enums\CalorieRange;
use App\Models\MealEntry;
use App\Models\NutritionGoal;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Support\Carbon;

it('snapshots the recipe name, calorie band and every macro scaled by servings', function () {
    $version = RecipeVersion::factory()
        ->for(Recipe::factory()->state(['name' => 'Chicken Bowl']))
        ->forCalorieRange(CalorieRange::From400)
        ->create([
            'calories' => 450,
            'protein_g' => 35.5,
            'carbs_g' => 40,
            'fat_g' => 12.3,
            'fiber_g' => 6,
            'sugar_g' => 4.1,
            'sodium_mg' => 801,
        ]);

    $entry = MealEntry::factory()->fromRecipeVersion($version, 1.5)->create()->refresh();

    expect($entry->recipe_version_id)->toBe($version->id)
        ->and($entry->name)->toBe('Chicken Bowl')
        ->and($entry->calorie_range)->toBe(CalorieRange::From400)
        ->and($entry->servings)->toBe('1.50')
        ->and($entry->calories)->toBe(675)
        ->and($entry->protein_g)->toBe('53.3')
        ->and($entry->carbs_g)->toBe('60.0')
        ->and($entry->fat_g)->toBe('18.5')
        ->and($entry->fiber_g)->toBe('9.0')
        ->and($entry->sugar_g)->toBe('6.2')
        ->and($entry->sodium_mg)->toBe(1202);
});

it('keeps unknown macros null instead of zero', function () {
    $version = RecipeVersion::factory()->create(['fiber_g' => null, 'sodium_mg' => null]);

    $entry = MealEntry::factory()->fromRecipeVersion($version, 2)->create()->refresh();

    expect($entry->fiber_g)->toBeNull()
        ->and($entry->sodium_mg)->toBeNull();
});

it('does not change a logged entry when the recipe version is edited later', function () {
    $version = RecipeVersion::factory()->create(['calories' => 300, 'protein_g' => 20]);
    $entry = MealEntry::factory()->fromRecipeVersion($version)->create();

    $version->update(['calories' => 500, 'protein_g' => 40]);

    expect($entry->refresh()->calories)->toBe(300)
        ->and($entry->protein_g)->toBe('20.0');
});

it('keeps logged entries when their recipe is deleted', function () {
    $version = RecipeVersion::factory()->create(['calories' => 300]);
    $entry = MealEntry::factory()->fromRecipeVersion($version)->create();

    $version->recipe->delete();

    expect($entry->refresh()->recipe_version_id)->toBeNull()
        ->and($entry->calories)->toBe(300);
});

it('uses the latest goal that started on or before the day', function () {
    $user = User::factory()->create();
    NutritionGoal::factory()->for($user)->create(['effective_from' => '2026-11-01', 'calories' => 2000]);
    NutritionGoal::factory()->for($user)->create(['effective_from' => '2026-11-10', 'calories' => 2200]);

    expect($user->goalOn(Carbon::parse('2026-10-31')))->toBeNull()
        ->and($user->goalOn(Carbon::parse('2026-11-09'))?->calories)->toBe(2000)
        ->and($user->goalOn(Carbon::parse('2026-11-10'))?->calories)->toBe(2200);
});

it('sums only the user\'s entries for that day against the goal in effect', function () {
    $user = User::factory()->create();
    $day = Carbon::parse('2026-11-14');
    NutritionGoal::factory()->for($user)->create(['effective_from' => '2026-11-01', 'calories' => 2000, 'protein_g' => 150]);
    $version = RecipeVersion::factory()->create([
        'calories' => 400, 'protein_g' => 30, 'carbs_g' => 50, 'fat_g' => 10, 'fiber_g' => null, 'sugar_g' => 5, 'sodium_mg' => 600,
    ]);
    MealEntry::factory()->for($user)->fromRecipeVersion($version)->create(['eaten_on' => $day]);
    MealEntry::factory()->for($user)->fromRecipeVersion($version, 0.5)->create(['eaten_on' => $day]);
    MealEntry::factory()->for($user)->fromRecipeVersion($version)->create(['eaten_on' => $day->copy()->subDay()]);
    MealEntry::factory()->fromRecipeVersion($version)->create(['eaten_on' => $day]);

    expect($user->nutritionSummaryFor($day))->toBe([
        'date' => '2026-11-14',
        'totals' => ['calories' => 600, 'protein_g' => 45.0, 'carbs_g' => 75.0, 'fat_g' => 15.0, 'fiber_g' => 0.0, 'sugar_g' => 7.5, 'sodium_mg' => 900],
        'targets' => ['calories' => 2000, 'protein_g' => 150.0, 'carbs_g' => null, 'fat_g' => null, 'fiber_g' => null, 'sugar_g' => null, 'sodium_mg' => null],
    ]);
});

it('returns zero totals and no targets for an empty day without a goal', function () {
    $user = User::factory()->create();

    $summary = $user->nutritionSummaryFor(Carbon::parse('2026-11-14'));

    expect($summary['totals']['calories'])->toBe(0)
        ->and($summary['targets']['calories'])->toBeNull();
});
