<?php

namespace App\Http\Controllers;

use App\Enums\MealType;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeSearchController extends Controller
{
    /**
     * Recipes the user can log, with their calorie versions. Filters by name and, unless
     * `all` is set, by the recipe tags that suit the chosen diary section.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();
        $mealType = $request->boolean('all') ? null : $request->enum('meal_type', MealType::class);

        $recipes = Recipe::query()
            ->whereHas('versions')
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', "%{$search}%"))
            ->when($mealType, fn (Builder $query, MealType $type) => $query->whereHas(
                'mealTypes',
                fn (Builder $tags) => $tags->whereIn('meal_type', $type->suggestedRecipeTags()),
            ))
            ->with('versions:id,recipe_id,calorie_range,calories,protein_g,carbs_g,fat_g')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'description', 'image_path']);

        return response()->json(['recipes' => $recipes]);
    }
}
