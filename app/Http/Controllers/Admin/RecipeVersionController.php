<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Recipes\SaveRecipeVersion;
use App\Enums\CalorieRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecipeVersionRequest;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeVersionController extends Controller
{
    /**
     * Show the form for adding a calorie version to a recipe.
     */
    public function create(Request $request, Recipe $recipe): Response
    {
        $ranges = collect($this->rangeOptions($recipe));
        $available = $ranges->where('available', true);
        $requested = $request->integer('range');

        return Inertia::render('admin/recipes/versions/create', [
            'recipe' => $recipe->only('id', 'name'),
            'calorieRanges' => $ranges->all(),
            'selectedRange' => $available->contains('value', $requested) ? $requested : $available->first()['value'] ?? null,
        ]);
    }

    /**
     * Store a new calorie version.
     */
    public function store(RecipeVersionRequest $request, Recipe $recipe, SaveRecipeVersion $saveRecipeVersion): RedirectResponse
    {
        $version = $saveRecipeVersion->handle($recipe, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':range kcal version added.', ['range' => $version->calorie_range->label()])]);

        return to_route('admin.recipes.show', [$recipe, 'range' => $version->calorie_range->value]);
    }

    /**
     * Show the form for editing a calorie version.
     */
    public function edit(Recipe $recipe, RecipeVersion $version): Response
    {
        $version->load(['ingredients', 'steps']);

        return Inertia::render('admin/recipes/versions/edit', [
            'recipe' => $recipe->only('id', 'name'),
            'version' => $version,
            'calorieRanges' => $this->rangeOptions($recipe, $version),
        ]);
    }

    /**
     * Update a calorie version.
     */
    public function update(RecipeVersionRequest $request, Recipe $recipe, RecipeVersion $version, SaveRecipeVersion $saveRecipeVersion): RedirectResponse
    {
        $saveRecipeVersion->handle($recipe, $request->validated(), $version);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':range kcal version updated.', ['range' => $version->calorie_range->label()])]);

        return to_route('admin.recipes.show', [$recipe, 'range' => $version->calorie_range->value]);
    }

    /**
     * Delete a calorie version.
     */
    public function destroy(Recipe $recipe, RecipeVersion $version): RedirectResponse
    {
        $version->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':range kcal version deleted.', ['range' => $version->calorie_range->label()])]);

        return to_route('admin.recipes.show', $recipe);
    }

    /**
     * Every calorie range, flagged as available unless another version of the recipe already uses it.
     *
     * @return list<array{value: int, label: string, available: bool}>
     */
    private function rangeOptions(Recipe $recipe, ?RecipeVersion $editing = null): array
    {
        $taken = $recipe->versions()
            ->when($editing, fn ($query) => $query->whereKeyNot($editing->id))
            ->pluck('calorie_range')
            ->map(fn (CalorieRange $range): int => $range->value);

        return array_map(
            fn (array $option): array => [...$option, 'available' => ! $taken->contains($option['value'])],
            RecipeController::calorieRangeOptions(),
        );
    }
}
