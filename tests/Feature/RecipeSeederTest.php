<?php

use App\Enums\CalorieRange;
use App\Enums\MealType;
use App\Models\Recipe;
use Database\Seeders\RecipeSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function importedRecipe(array $overrides = []): array
{
    return array_merge([
        'name' => 'Tuna & Cucumber Sandwich',
        'slug' => 'tuna-cucumber-sandwich',
        'meal_types' => ['lunch'],
        'image' => 'images/tuna-cucumber-sandwich.jpg',
        'versions' => [
            [
                'calorie_range' => 200,
                'calories' => 218,
                'protein_g' => 22.0,
                'carbs_g' => 15.0,
                'fat_g' => 8.0,
                'fiber_g' => 4.0,
                'sugar_g' => null,
                'sodium_mg' => 361,
                'prep_minutes' => 10,
                'ingredients' => [
                    ['name' => 'Canned In Water Tuna', 'quantity' => 85.0, 'unit' => 'grams', 'note' => '3 oz'],
                    ['name' => 'Mayonnaise', 'quantity' => 1.5, 'unit' => 'tsps', 'note' => null],
                ],
                'steps' => ['Mix the tuna and mayonnaise.', 'Assemble the sandwich.'],
                'notes' => 'Aim for a slice of bread between 70 and 100 calories.',
            ],
        ],
    ], $overrides);
}

function putImport(array $recipes): void
{
    Storage::disk('local')->put('recipe-import/recipes.json', json_encode($recipes));
    Storage::disk('local')->put('recipe-import/images/tuna-cucumber-sandwich.jpg', 'jpeg-bytes');
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('imports recipes with their versions, ingredients, steps, and photo', function () {
    putImport([importedRecipe()]);

    $this->seed(RecipeSeeder::class);

    $recipe = Recipe::sole();
    $version = $recipe->versions()->sole();
    expect($recipe->name)->toBe('Tuna & Cucumber Sandwich')
        ->and($recipe->image_path)->toBe('recipes/tuna-cucumber-sandwich.jpg')
        ->and($recipe->mealTypes->pluck('meal_type')->all())->toBe([MealType::Lunch])
        ->and($version->calorie_range)->toBe(CalorieRange::From200)
        ->and($version->calories)->toBe(218)
        ->and($version->prep_minutes)->toBe(10)
        ->and($version->ingredients->map->only('position', 'name', 'quantity', 'unit')->all())->toBe([
            ['position' => 1, 'name' => 'Canned In Water Tuna', 'quantity' => '85.00', 'unit' => 'grams'],
            ['position' => 2, 'name' => 'Mayonnaise', 'quantity' => '1.50', 'unit' => 'tsps'],
        ])
        ->and($version->steps->pluck('instruction')->all())->toBe(['Mix the tuna and mayonnaise.', 'Assemble the sandwich.']);
    Storage::disk('public')->assertExists('recipes/tuna-cucumber-sandwich.jpg');
});

it('updates existing recipes instead of duplicating them when run again', function () {
    putImport([importedRecipe()]);
    $this->seed(RecipeSeeder::class);
    $recipe = Recipe::sole();
    $recipe->update(['description' => 'Edited in the admin.']);

    $updated = importedRecipe();
    $updated['versions'][0]['calories'] = 230;
    $updated['meal_types'] = ['lunch', 'dinner'];
    putImport([$updated]);
    $this->seed(RecipeSeeder::class);

    $recipe->refresh();
    expect(Recipe::count())->toBe(1)
        ->and($recipe->versions()->count())->toBe(1)
        ->and($recipe->versions()->sole()->calories)->toBe(230)
        ->and($recipe->versions()->sole()->ingredients()->count())->toBe(2)
        ->and($recipe->mealTypes()->count())->toBe(2)
        ->and($recipe->description)->toBe('Edited in the admin.');
});

it('stores snacks under 200 kcal in the under 200 range', function () {
    $snack = importedRecipe(['name' => 'Yogurt with Berries', 'slug' => 'yogurt-with-berries', 'meal_types' => ['high_protein_snack'], 'image' => null]);
    $snack['versions'][0]['calorie_range'] = 0;
    $snack['versions'][0]['calories'] = 164;
    putImport([$snack]);

    $this->seed(RecipeSeeder::class);

    expect(Recipe::sole()->versions()->sole()->calorie_range)->toBe(CalorieRange::Under200);
});

it('skips the import when the data file is missing', function () {
    $this->seed(RecipeSeeder::class);

    expect(Recipe::count())->toBe(0);
});
