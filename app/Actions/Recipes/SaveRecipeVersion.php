<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveRecipeVersion
{
    /**
     * Create or update a recipe version, replacing its ingredients and steps
     * with the given lists in the order they were submitted.
     *
     * @param  array<string, mixed>  $attributes  Validated version fields plus `ingredients` and `steps` lists.
     */
    public function handle(Recipe $recipe, array $attributes, ?RecipeVersion $version = null): RecipeVersion
    {
        return DB::transaction(function () use ($recipe, $attributes, $version): RecipeVersion {
            $version ??= $recipe->versions()->make();

            $version->fill(Arr::except($attributes, ['ingredients', 'steps']))->save();

            $version->ingredients()->delete();
            $version->steps()->delete();

            $version->ingredients()->createMany(
                $this->positioned(Arr::array($attributes, 'ingredients'), ['name', 'quantity', 'unit', 'note']),
            );

            $version->steps()->createMany(
                $this->positioned(Arr::array($attributes, 'steps'), ['instruction']),
            );

            return $version;
        });
    }

    /**
     * Keep only the given fields of each row and number the rows from 1.
     *
     * @param  array<array-key, mixed>  $rows
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private function positioned(array $rows, array $fields): array
    {
        return array_map(
            fn (mixed $row, int $index): array => [
                'position' => $index + 1,
                ...Arr::only(is_array($row) ? $row : [], $fields),
            ],
            array_values($rows),
            array_keys(array_values($rows)),
        );
    }
}
