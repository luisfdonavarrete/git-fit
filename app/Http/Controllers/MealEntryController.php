<?php

namespace App\Http\Controllers;

use App\Enums\MealType;
use App\Http\Requests\StoreMealEntryRequest;
use App\Http\Requests\UpdateMealEntryRequest;
use App\Models\MealEntry;
use App\Models\RecipeVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MealEntryController extends Controller
{
    /**
     * Log a recipe version to a section of the user's diary.
     */
    public function store(StoreMealEntryRequest $request): RedirectResponse
    {
        $version = RecipeVersion::with('recipe:id,name')->findOrFail($request->integer('recipe_version_id'));

        $entry = $request->user()->mealEntries()
            ->make($request->safe()->only(['eaten_on', 'meal_type']))
            ->fillFromRecipeVersion($version, $request->float('servings'));
        $entry->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to :meal.', [
            'name' => $entry->name,
            'meal' => mb_strtolower($entry->meal_type->label()),
        ])]);

        return back();
    }

    /**
     * Change an entry's servings or move it to another section.
     */
    public function update(UpdateMealEntryRequest $request, MealEntry $mealEntry): RedirectResponse
    {
        Gate::authorize('update', $mealEntry);

        $mealEntry->meal_type = $request->enum('meal_type', MealType::class) ?? $mealEntry->meal_type;
        $mealEntry->rescaleTo($request->float('servings'));
        $mealEntry->save();

        return back();
    }

    /**
     * Remove an entry from the diary.
     */
    public function destroy(MealEntry $mealEntry): RedirectResponse
    {
        Gate::authorize('delete', $mealEntry);

        $mealEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name removed.', ['name' => $mealEntry->name])]);

        return back();
    }
}
