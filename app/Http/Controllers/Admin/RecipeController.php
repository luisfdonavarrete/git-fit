<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CalorieRange;
use App\Enums\DietaryTag;
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
     * List recipes, optionally filtered by name, meal type, calorie range and diet.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $mealType = $request->enum('meal_type', MealType::class);
        $calorieRange = $request->enum('calorie_range', CalorieRange::class);
        $diets = array_values(array_unique($request->enums('diets', DietaryTag::class), SORT_REGULAR));

        $recipes = Recipe::query()
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', "%{$search}%"))
            ->when($mealType, fn (Builder $query, MealType $type) => $query->forMealType($type))
            ->when($calorieRange, fn (Builder $query, CalorieRange $range) => $query->withVersionInRange($range))
            ->when($diets !== [], fn (Builder $query) => $query->meetsDiets($diets))
            ->with([
                'versions:id,recipe_id,calorie_range,calories',
                'mealTypes:id,recipe_id,meal_type',
                'dietaryTags:id,recipe_id,dietary_tag',
            ])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('admin/recipes/index', [
            'recipes' => $recipes,
            'filters' => [
                'search' => $search,
                'meal_type' => $mealType?->value,
                'calorie_range' => $calorieRange?->value,
                'diets' => array_map(fn (DietaryTag $tag): string => $tag->value, $diets),
            ],
            'calorieRanges' => self::calorieRangeOptions(),
            'mealTypes' => MealType::options(),
            'dietaryTags' => DietaryTag::options(),
        ]);
    }

    /**
     * Show the form for creating a recipe.
     */
    public function create(): Response
    {
        return Inertia::render('admin/recipes/create', [
            'mealTypes' => MealType::options(),
            'dietaryTags' => DietaryTag::options(),
        ]);
    }

    /**
     * Store a new recipe.
     */
    public function store(RecipeRequest $request): RedirectResponse
    {
        $recipe = DB::transaction(function () use ($request): Recipe {
            $recipe = Recipe::create(Arr::except($request->validated(), ['meal_types', 'dietary_tags']));
            $recipe->syncMealTypes($request->mealTypes());
            $recipe->syncDietaryTags($request->dietaryTags());

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
        $recipe->load(['versions.ingredients', 'versions.steps', 'mealTypes', 'dietaryTags']);

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
            'recipe' => $recipe->load(['mealTypes', 'dietaryTags']),
            'mealTypes' => MealType::options(),
            'dietaryTags' => DietaryTag::options(),
        ]);
    }

    /**
     * Update a recipe's shared details.
     */
    public function update(RecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        DB::transaction(function () use ($request, $recipe): void {
            $recipe->update(Arr::except($request->validated(), ['meal_types', 'dietary_tags']));
            $recipe->syncMealTypes($request->mealTypes());
            $recipe->syncDietaryTags($request->dietaryTags());
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
