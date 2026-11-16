<?php

namespace App\Actions\MealPlans;

use App\Actions\Coach\CalculateCoachTargets;
use App\Enums\MealEntryStatus;
use App\Enums\MealType;
use App\Models\MealEntry;
use App\Models\NutritionGoal;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan the rest of a day's meals (breakfast, lunch, dinner and two snacks) to fit the day's goal.
 * Slots that already have eaten food are skipped and that food is subtracted from the target.
 * The plan replaces the day's earlier pending entries; nothing counts until the user confirms it.
 */
class GenerateMealPlan
{
    /**
     * The meals a full day is planned with.
     *
     * @var list<MealType>
     */
    public const array DAY_SLOTS = [MealType::Breakfast, MealType::Lunch, MealType::Dinner, MealType::Snack, MealType::Snack];

    /**
     * @return array{entries: Collection<int, MealEntry>, fits_calorie_window: bool}
     *
     * @throws ValidationException When the day has no goal or no open slots.
     * @throws MealPlanFailed When the planner can't produce a valid plan.
     */
    public function handle(User $user, CarbonInterface $date, MealPlanner $planner): array
    {
        $goal = $user->goalOn($date) ?? throw ValidationException::withMessages([
            'meal_plan' => __('Set a nutrition goal before planning a day.'),
        ]);

        $eaten = $user->mealEntries()->eaten()->onDate($date)->with('recipeVersion:id,recipe_id')->get();
        $slots = $this->openSlots($eaten);

        if ($slots === []) {
            throw ValidationException::withMessages([
                'meal_plan' => __('Every meal on this day is already logged.'),
            ]);
        }

        $request = $this->buildRequest($user, $goal, $slots, $eaten);
        $versions = $this->validatedVersions($request, $planner->plan($request));

        $entries = DB::transaction(function () use ($user, $date, $slots, $versions): Collection {
            $user->mealEntries()->pending()->onDate($date)->delete();

            $entries = new Collection;

            foreach ($slots as $index => $slot) {
                $entry = $user->mealEntries()
                    ->make(['eaten_on' => $date->toDateString(), 'meal_type' => $slot])
                    ->fillFromRecipeVersion($versions[$index]);
                $entry->status = MealEntryStatus::Pending;
                $entry->save();
                $entries->push($entry);
            }

            return $entries;
        });

        $plannedCalories = (int) $entries->sum('calories');
        [$minCalories, $maxCalories] = $request->calorieWindow;

        return [
            'entries' => $entries,
            'fits_calorie_window' => $plannedCalories >= $minCalories && $plannedCalories <= $maxCalories,
        ];
    }

    /**
     * The day's slots minus those already covered by eaten food; each eaten snack fills one snack slot.
     *
     * @param  Collection<int, MealEntry>  $eaten
     * @return list<MealType>
     */
    private function openSlots(Collection $eaten): array
    {
        $eatenCounts = $eaten->countBy(fn (MealEntry $entry): string => $entry->meal_type->value);
        $slots = [];
        $seen = [];

        foreach (self::DAY_SLOTS as $slot) {
            $seen[$slot->value] = ($seen[$slot->value] ?? 0) + 1;

            if ($seen[$slot->value] > ($eatenCounts->get($slot->value) ?? 0)) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * @param  list<MealType>  $slots
     * @param  Collection<int, MealEntry>  $eaten
     */
    private function buildRequest(User $user, NutritionGoal $goal, array $slots, Collection $eaten): MealPlanRequest
    {
        $eatenCalories = (int) $eaten->sum('calories');
        $eatenProtein = (float) $eaten->sum(fn (MealEntry $entry): float => (float) $entry->protein_g);
        $profile = $user->coachProfile;

        [$minCalories, $maxCalories] = $profile === null
            ? [$goal->calories - 100, $goal->calories + 100]
            : CalculateCoachTargets::calorieRange($goal->calories, $profile->sex);

        $eatenRecipeIds = array_values($eaten
            ->map(fn (MealEntry $entry): ?int => $entry->recipeVersion?->recipe_id)
            ->filter()
            ->all());

        return new MealPlanRequest(
            slots: $slots,
            candidates: $this->candidates($slots, $eatenRecipeIds),
            targetCalories: max(0, $goal->calories - $eatenCalories),
            calorieWindow: [max(0, $minCalories - $eatenCalories), max(0, $maxCalories - $eatenCalories)],
            targetProtein: max(0.0, round((float) $goal->protein_g - $eatenProtein, 1)),
        );
    }

    /**
     * Recipes (with their calorie versions) that suit each open slot, leaving out ones already eaten today.
     *
     * @param  list<MealType>  $slots
     * @param  list<int>  $excludedRecipeIds
     * @return array<string, list<array{recipe_id: int, name: string, versions: list<array{id: int, calories: int, protein_g: float}>}>>
     */
    private function candidates(array $slots, array $excludedRecipeIds): array
    {
        $candidates = [];

        foreach (array_unique(array_map(fn (MealType $slot): string => $slot->value, $slots)) as $value) {
            $slot = MealType::from($value);

            $candidates[$value] = array_values(Recipe::query()
                ->whereHas('mealTypes', fn (Builder $tags) => $tags->whereIn('meal_type', $slot->suggestedRecipeTags()))
                ->whereHas('versions')
                ->whereNotIn('id', $excludedRecipeIds)
                ->with('versions:id,recipe_id,calorie_range,calories,protein_g')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Recipe $recipe): array => [
                    'recipe_id' => $recipe->id,
                    'name' => $recipe->name,
                    'versions' => array_values($recipe->versions->map(fn (RecipeVersion $version): array => [
                        'id' => $version->id,
                        'calories' => $version->calories,
                        'protein_g' => (float) $version->protein_g,
                    ])->all()),
                ])
                ->all());
        }

        return $candidates;
    }

    /**
     * Check the planner's picks: one per slot, each a candidate for its slot, no recipe twice.
     *
     * @param  list<int>  $versionIds
     * @return list<RecipeVersion>
     *
     * @throws MealPlanFailed
     */
    private function validatedVersions(MealPlanRequest $request, array $versionIds): array
    {
        if (count($versionIds) !== count($request->slots)) {
            throw new MealPlanFailed(__('The planner returned the wrong number of meals.'));
        }

        $versions = RecipeVersion::with('recipe:id,name')->findMany($versionIds)->keyBy('id');
        $usedRecipeIds = [];
        $picked = [];

        foreach ($request->slots as $index => $slot) {
            $version = $versions->get($versionIds[$index]);
            $isCandidate = $version !== null && collect($request->candidatesFor($slot))
                ->contains(fn (array $recipe): bool => $recipe['recipe_id'] === $version->recipe_id);

            if (! $isCandidate || in_array($version->recipe_id, $usedRecipeIds, true)) {
                throw new MealPlanFailed(__('The planner picked a recipe that doesn\'t fit the :meal slot.', [
                    'meal' => mb_strtolower($slot->label()),
                ]));
            }

            $usedRecipeIds[] = $version->recipe_id;
            $picked[] = $version;
        }

        return $picked;
    }
}
