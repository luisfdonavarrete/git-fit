<?php

namespace Database\Seeders;

use App\Actions\Recipes\SaveRecipeVersion;
use App\Enums\CalorieRange;
use App\Enums\MealType;
use App\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Imports recipes from the extracted `recipes.json` (see config/recipes.php).
 *
 * Safe to run repeatedly: recipes are matched by slug and versions by calorie
 * range, so re-running updates existing rows instead of duplicating them.
 * Recipe descriptions edited in the admin are left alone.
 */
class RecipeSeeder extends Seeder
{
    public function __construct(private SaveRecipeVersion $saveRecipeVersion) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $disk = Storage::disk(config()->string('recipes.import.disk'));
        $path = trim(config()->string('recipes.import.path'), '/');

        if (! $disk->exists("{$path}/recipes.json")) {
            $this->command?->warn("Skipping recipes: {$path}/recipes.json not found on the [".config('recipes.import.disk').'] disk.');

            return;
        }

        /** @var list<array<string, mixed>> $recipes */
        $recipes = json_decode((string) $disk->get("{$path}/recipes.json"), true, flags: JSON_THROW_ON_ERROR);

        foreach ($recipes as $data) {
            DB::transaction(fn () => $this->importRecipe($data, $path));
        }

        $this->command?->info(sprintf('Imported %d recipes.', count($recipes)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importRecipe(array $data, string $importPath): void
    {
        $recipe = Recipe::firstOrNew(['slug' => Arr::string($data, 'slug')]);
        $recipe->name = Arr::string($data, 'name');
        $recipe->image_path = $this->copyImage(Arr::get($data, 'image'), $importPath, $recipe->slug) ?? $recipe->image_path;
        $recipe->save();

        $recipe->syncMealTypes(array_map(
            fn (mixed $value): MealType => MealType::from((string) $value),
            Arr::array($data, 'meal_types'),
        ));

        foreach (Arr::array($data, 'versions') as $version) {
            $attributes = Arr::wrap($version);
            $range = CalorieRange::from((int) $attributes['calorie_range']);
            $attributes['steps'] = array_map(
                fn (mixed $step): array => ['instruction' => (string) $step],
                Arr::array($attributes, 'steps'),
            );

            $this->saveRecipeVersion->handle(
                $recipe,
                Arr::only($attributes, [
                    'calorie_range', 'calories', 'prep_minutes', 'protein_g', 'carbs_g', 'fat_g',
                    'fiber_g', 'sugar_g', 'sodium_mg', 'notes', 'ingredients', 'steps',
                ]),
                $recipe->versions()->where('calorie_range', $range)->first(),
            );
        }
    }

    /**
     * Copy a recipe photo from the import disk to the images disk.
     *
     * @return string|null The stored path, or null when there is no photo.
     */
    private function copyImage(mixed $image, string $importPath, string $slug): ?string
    {
        if (! is_string($image) || $image === '') {
            return null;
        }

        $source = Storage::disk(config()->string('recipes.import.disk'));

        if (! $source->exists("{$importPath}/{$image}")) {
            return null;
        }

        $target = "recipes/{$slug}.".pathinfo($image, PATHINFO_EXTENSION);

        Storage::disk(config()->string('recipes.images_disk'))
            ->put($target, (string) $source->get("{$importPath}/{$image}"), 'public');

        return $target;
    }
}
