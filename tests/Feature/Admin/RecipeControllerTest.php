<?php

use App\Enums\CalorieRange;
use App\Enums\DietaryTag;
use App\Enums\MealType;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('recipes can be filtered by calorie range', function () {
    $light = Recipe::factory()->create(['name' => 'Berry Oats']);
    RecipeVersion::factory()->for($light)->forCalorieRange(CalorieRange::From300)->create();
    $hearty = Recipe::factory()->create(['name' => 'Salmon Tray Bake']);
    RecipeVersion::factory()->for($hearty)->forCalorieRange(CalorieRange::From600)->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.index', ['calorie_range' => 300]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Berry Oats')
        ->where('filters.calorie_range', 300)
    );
});

test('recipes can be filtered by every selected diet', function () {
    Recipe::factory()->withDietaryTags(DietaryTag::Vegan, DietaryTag::GlutenFree)->create(['name' => 'Lentil Curry']);
    Recipe::factory()->withDietaryTags(DietaryTag::Vegan)->create(['name' => 'Tofu Toast']);
    Recipe::factory()->withDietaryTags(DietaryTag::GlutenFree)->create(['name' => 'Steak Salad']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.index', ['diets' => ['vegan', 'gluten_free']]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Lentil Curry')
        ->has('recipes.data.0.dietary_tags', 2)
        ->where('filters.diets', ['vegan', 'gluten_free'])
    );
});

test('vegan recipes match the vegetarian and dairy-free filters', function (string $diet) {
    Recipe::factory()->withDietaryTags(DietaryTag::Vegan)->create(['name' => 'Tofu Toast']);
    Recipe::factory()->create(['name' => 'Steak Salad']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.index', ['diets' => [$diet]]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recipes.data', 1)
        ->where('recipes.data.0.name', 'Tofu Toast')
    );
})->with(['vegetarian', 'dairy_free']);

test('vegetarian recipes do not match the vegan filter', function () {
    Recipe::factory()->withDietaryTags(DietaryTag::Vegetarian)->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.index', ['diets' => ['vegan']]));

    $response->assertInertia(fn (Assert $page) => $page->has('recipes.data', 0));
});

test('unknown filter values are ignored', function () {
    Recipe::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.recipes.index', ['calorie_range' => 250, 'diets' => ['keto']]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recipes.data', 1)
        ->where('filters.calorie_range', null)
        ->where('filters.diets', [])
    );
});

test('a recipe can be created with dietary tags', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Lentil Curry',
        'dietary_tags' => ['vegan', 'gluten_free'],
    ]);

    $response->assertSessionHasNoErrors();
    expect(Recipe::sole()->dietaryTags->pluck('dietary_tag')->all())
        ->toEqualCanonicalizing([DietaryTag::Vegan, DietaryTag::GlutenFree]);
});

test('updating a recipe replaces its dietary tags', function () {
    $recipe = Recipe::factory()->withDietaryTags(DietaryTag::Vegan, DietaryTag::GlutenFree)->create();

    $response = $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => $recipe->name,
        'dietary_tags' => ['vegetarian'],
    ]);

    $response->assertSessionHasNoErrors();
    expect($recipe->dietaryTags()->pluck('dietary_tag')->all())->toBe([DietaryTag::Vegetarian]);
});

test('an unknown dietary tag is rejected', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Mystery Dish',
        'dietary_tags' => ['keto'],
    ]);

    $response->assertSessionHasErrors(['dietary_tags.0' => 'The selected dietary tag is invalid.']);
    expect(Recipe::count())->toBe(0);
});

test('a recipe can be created with an uploaded image', function () {
    Storage::fake(config('recipes.images_disk'));

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Chicken Bowl',
        'image' => UploadedFile::fake()->image('bowl.jpg'),
    ]);

    $response->assertSessionHasNoErrors();
    $recipe = Recipe::sole();
    expect($recipe->image_path)->toStartWith('recipes/');
    Storage::disk(config('recipes.images_disk'))->assertExists($recipe->image_path);
});

test('uploading a new image replaces the stored one', function () {
    $disk = Storage::fake(config('recipes.images_disk'));
    $disk->put('recipes/old.jpg', 'old-bytes');
    $recipe = Recipe::factory()->create(['image_path' => 'recipes/old.jpg']);

    $response = $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => $recipe->name,
        'image' => UploadedFile::fake()->image('new.png'),
    ]);

    $response->assertSessionHasNoErrors();
    $disk->assertMissing('recipes/old.jpg');
    $disk->assertExists($recipe->refresh()->image_path);
    expect($recipe->image_path)->not->toBe('recipes/old.jpg');
});

test('updating a recipe without an upload keeps its image', function () {
    $recipe = Recipe::factory()->create(['image_path' => 'https://example.com/bowl.jpg']);

    $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => 'Renamed Bowl',
    ])->assertSessionHasNoErrors();

    expect($recipe->refresh()->image_path)->toBe('https://example.com/bowl.jpg');
});

test('a recipe image can be removed', function () {
    $disk = Storage::fake(config('recipes.images_disk'));
    $disk->put('recipes/old.jpg', 'old-bytes');
    $recipe = Recipe::factory()->create(['image_path' => 'recipes/old.jpg']);

    $response = $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => $recipe->name,
        'remove_image' => '1',
    ]);

    $response->assertSessionHasNoErrors();
    $disk->assertMissing('recipes/old.jpg');
    expect($recipe->refresh()->image_path)->toBeNull();
});

test('a recipe image must be an image file', function () {
    Storage::fake(config('recipes.images_disk'));

    $response = $this->actingAs(User::factory()->create())->post(route('admin.recipes.store'), [
        'name' => 'Chicken Bowl',
        'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('image');
    expect(Recipe::count())->toBe(0);
});

test('the image path cannot be set directly', function () {
    $recipe = Recipe::factory()->create(['image_path' => null]);

    $this->actingAs(User::factory()->create())->put(route('admin.recipes.update', $recipe), [
        'name' => $recipe->name,
        'image_path' => 'https://example.com/elsewhere.jpg',
    ])->assertSessionHasNoErrors();

    expect($recipe->refresh()->image_path)->toBeNull();
});
