<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CalorieRange;
use App\Enums\MealType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecipeRequest;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    /**
     * List recipes, optionally filtered by name.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $mealType = $request->enum('meal_type', MealType::class);

        $recipes = Recipe::query()
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', "%{$search}%"))
            ->when($mealType, fn (Builder $query, MealType $type) => $query->forMealType($type))
            ->with(['versions:id,recipe_id,calorie_range,calories', 'mealTypes:id,recipe_id,meal_type'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('admin/recipes/index', [
            'recipes' => $recipes,
            'filters' => ['search' => $search, 'meal_type' => $mealType?->value],
            'calorieRanges' => self::calorieRangeOptions(),
            'mealTypes' => MealType::options(),
        ]);
    }

    /**
     * Show the form for creating a recipe.
     */
    public function create(): Response
    {
        return Inertia::render('admin/recipes/create', [
            'mealTypes' => MealType::options(),
        ]);
    }

    /**
     * Store a new recipe.
     */
    public function store(RecipeRequest $request): RedirectResponse
    {
        $recipe = DB::transaction(function () use ($request): Recipe {
            $recipe = Recipe::create(Arr::except($request->validated(), 'meal_types'));
            $recipe->syncMealTypes($request->mealTypes());

            return $recipe;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recipe created. Add its first calorie version.')]);

        return to_route('admin.recipes.show', $recipe);
    }

    /**
     * Show a recipe with all of its calorie versions.
     */
    public function show(Recipe $recipe): Response
    {
        $recipe->load(['versions.ingredients', 'versions.steps', 'mealTypes']);

        return Inertia::render('admin/recipes/show', [
            'recipe' => $recipe,
            'calorieRanges' => self::calorieRangeOptions(),
            'mealTypes' => MealType::options(),
        ]);
    }

    /**
     * Show the form for editing a recipe.
     */
    public function edit(Recipe $recipe): Response
    {
        return Inertia::render('admin/recipes/edit', [
            'recipe' => $recipe->load('mealTypes'),
            'mealTypes' => MealType::options(),
        ]);
    }

    /**
     * Update a recipe's shared details.
     */
    public function update(RecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        DB::transaction(function () use ($request, $recipe): void {
            $recipe->update(Arr::except($request->validated(), 'meal_types'));
            $recipe->syncMealTypes($request->mealTypes());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recipe updated.')]);

        return to_route('admin.recipes.show', $recipe);
    }

    /**
     * Delete a recipe and all of its versions.
     */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recipe deleted.')]);

        return to_route('admin.recipes.index');
    }

    /**
     * Every calorie range with its display label, lowest first.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function calorieRangeOptions(): array
    {
        return array_map(
            fn (CalorieRange $range): array => ['value' => $range->value, 'label' => $range->label()],
            CalorieRange::cases(),
        );
    }
}
