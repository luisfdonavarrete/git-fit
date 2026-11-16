<?php

use App\Actions\MealPlans\ClaudeMealPlanClient;
use App\Actions\MealPlans\GenerateMealPlan;
use App\Actions\MealPlans\MealPlanFailed;
use App\Enums\CalorieRange;
use App\Enums\GoalSource;
use App\Enums\MealEntryStatus;
use App\Enums\MealType;
use App\Models\CoachProfile;
use App\Models\MealEntry;
use App\Models\NutritionGoal;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\mock;

/**
 * A recipe tagged for one meal, with one version per given calorie count.
 *
 * @param  list<int>  $calories
 */
function planRecipe(MealType $mealType, array $calories, float $protein = 25): Recipe
{
    $recipe = Recipe::factory()->withMealTypes($mealType)->create();

    foreach ($calories as $kcal) {
        RecipeVersion::factory()->for($recipe)
            ->forCalorieRange($kcal < 200 ? CalorieRange::Under200 : CalorieRange::from(min(800, intdiv($kcal, 100) * 100)))
            ->create(['calories' => $kcal, 'protein_g' => $protein]);
    }

    return $recipe;
}

/**
 * Enough recipes to plan a 2,000 kcal day: about 500 breakfast, 600 lunch, 600 dinner, 150 per snack.
 *
 * @return array<string, Recipe>
 */
function planLibrary(): array
{
    return [
        'breakfast' => planRecipe(MealType::Breakfast, [400, 500]),
        'lunch' => planRecipe(MealType::Lunch, [500, 600]),
        'dinner' => planRecipe(MealType::Dinner, [600, 700]),
        'snack' => planRecipe(MealType::Snack, [100, 200]),
        'protein_snack' => planRecipe(MealType::HighProteinSnack, [150, 250]),
    ];
}

function userWithGoal(int $calories = 2000, float $protein = 120): User
{
    $user = User::factory()->create();
    NutritionGoal::factory()->for($user)->create(['effective_from' => '2026-11-01', 'calories' => $calories, 'protein_g' => $protein]);

    return $user;
}

beforeEach(fn () => $this->travelTo('2026-11-16 08:00'));

it('plans breakfast, lunch, dinner and two different snacks as pending entries within the calorie range', function () {
    planLibrary();
    $user = userWithGoal();

    $response = $this->actingAs($user)->from(route('dashboard'))->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'search']);

    $response->assertRedirect(route('dashboard'))->assertInertiaFlash('toast.type', 'success');
    $entries = $user->mealEntries()->get();
    expect($entries)->toHaveCount(5)
        ->and($entries->every(fn (MealEntry $entry): bool => $entry->status === MealEntryStatus::Pending))->toBeTrue()
        ->and($entries->map(fn (MealEntry $entry): string => $entry->meal_type->value)->sort()->values()->all())
        ->toBe(['breakfast', 'dinner', 'lunch', 'snack', 'snack'])
        ->and($entries->where('meal_type', MealType::Snack)->pluck('name')->unique())->toHaveCount(2)
        ->and((int) $entries->sum('calories'))->toBeGreaterThanOrEqual(1900)->toBeLessThanOrEqual(2100)
        ->and($user->nutritionSummaryFor(Carbon::parse('2026-11-16'))['totals']['calories'])->toBe(0);
});

it('plans only the open slots against what is left once eaten food is subtracted', function () {
    $library = planLibrary();
    $user = userWithGoal();
    MealEntry::factory()->for($user)
        ->fromRecipeVersion($library['breakfast']->versions()->where('calories', 500)->sole())
        ->create(['eaten_on' => '2026-11-16', 'meal_type' => MealType::Breakfast]);
    MealEntry::factory()->for($user)
        ->fromRecipeVersion($library['snack']->versions()->where('calories', 100)->sole())
        ->create(['eaten_on' => '2026-11-16', 'meal_type' => MealType::Snack]);

    $this->actingAs($user)->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'search']);

    $planned = $user->mealEntries()->pending()->get();
    expect($planned->map(fn (MealEntry $entry): string => $entry->meal_type->value)->sort()->values()->all())
        ->toBe(['dinner', 'lunch', 'snack'])
        ->and($planned->pluck('name'))->not->toContain($library['snack']->name)
        ->and((int) $user->mealEntries()->sum('calories'))->toBeGreaterThanOrEqual(1900)->toBeLessThanOrEqual(2100);
});

it('replaces the day\'s earlier plan without touching eaten entries or other days', function () {
    planLibrary();
    $user = userWithGoal();
    $eaten = MealEntry::factory()->for($user)->create(['eaten_on' => '2026-11-16', 'meal_type' => MealType::Lunch]);
    $oldPlan = MealEntry::factory()->for($user)->pending()->create(['eaten_on' => '2026-11-16', 'meal_type' => MealType::Dinner]);
    $otherDay = MealEntry::factory()->for($user)->pending()->create(['eaten_on' => '2026-11-17', 'meal_type' => MealType::Dinner]);

    $this->actingAs($user)->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'search']);

    expect(MealEntry::find($oldPlan->id))->toBeNull()
        ->and(MealEntry::find($eaten->id))->not->toBeNull()
        ->and(MealEntry::find($otherDay->id))->not->toBeNull()
        ->and($user->mealEntries()->pending()->onDate(Carbon::parse('2026-11-16'))->count())->toBe(4);
});

it('warns when no combination fits the calorie range and saves the closest match', function () {
    planRecipe(MealType::Breakfast, [150]);
    planRecipe(MealType::Lunch, [150]);
    planRecipe(MealType::Dinner, [150]);
    planRecipe(MealType::Snack, [100]);
    planRecipe(MealType::Snack, [100]);
    $user = userWithGoal(calories: 2000);

    $this->actingAs($user)->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'search'])
        ->assertInertiaFlash('toast.type', 'warning');

    expect($user->mealEntries()->pending()->count())->toBe(5);
});

it('refuses to plan a day it cannot fill', function (Closure $arrange, string $message) {
    $user = $arrange();

    $this->actingAs($user)->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'search'])
        ->assertSessionHasErrors(['meal_plan' => $message]);

    expect($user->mealEntries()->pending()->count())->toBe(0);
})->with([
    'no goal' => [fn () => tap(User::factory()->create(), fn () => planLibrary()), 'Set a nutrition goal before planning a day.'],
    'every meal already eaten' => [function () {
        $user = userWithGoal();
        foreach (GenerateMealPlan::DAY_SLOTS as $mealType) {
            MealEntry::factory()->for($user)->create(['eaten_on' => '2026-11-16', 'meal_type' => $mealType]);
        }

        return $user;
    }, 'Every meal on this day is already logged.'],
]);

it('shows an error and saves nothing when a slot has no recipes', function () {
    planRecipe(MealType::Breakfast, [500]);
    $user = userWithGoal();

    $this->actingAs($user)->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'search'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($user->mealEntries()->count())->toBe(0);
});

it('rejects an unknown planner, or claude without an api key', function (string $planner) {
    config(['services.anthropic.key' => null]);

    $this->actingAs(userWithGoal())->post(route('meal-plans.store', '2026-11-16'), ['planner' => $planner])
        ->assertSessionHasErrors('planner');
})->with(['claude', 'magic']);

it('returns not found for an impossible date', function () {
    $this->actingAs(userWithGoal())->post(route('meal-plans.store', '2026-02-30'), ['planner' => 'search'])
        ->assertNotFound();
});

it('saves the recipe versions claude picks', function () {
    config(['services.anthropic.key' => 'test-key']);
    $library = planLibrary();
    $user = userWithGoal();
    $picks = [
        $library['breakfast']->versions()->where('calories', 500)->sole()->id,
        $library['lunch']->versions()->where('calories', 600)->sole()->id,
        $library['dinner']->versions()->where('calories', 600)->sole()->id,
        $library['snack']->versions()->where('calories', 100)->sole()->id,
        $library['protein_snack']->versions()->where('calories', 150)->sole()->id,
    ];
    mock(ClaudeMealPlanClient::class)->shouldReceive('complete')->once()->andReturn(json_encode([
        'picks' => array_map(fn (int $id, int $slot): array => ['slot' => $slot, 'recipe_version_id' => $id], $picks, array_keys($picks)),
        'note' => 'Balanced day.',
    ]));

    $this->actingAs($user)->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'claude'])
        ->assertInertiaFlash('toast.type', 'success');

    expect($user->mealEntries()->pending()->orderBy('id')->pluck('recipe_version_id')->all())->toBe($picks);
});

it('shows an error and saves nothing when claude fails or picks something invalid', function (Closure $reply) {
    config(['services.anthropic.key' => 'test-key']);
    planLibrary();
    $user = userWithGoal();
    $reply(mock(ClaudeMealPlanClient::class)->shouldReceive('complete')->once());

    $this->actingAs($user)->post(route('meal-plans.store', '2026-11-16'), ['planner' => 'claude'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($user->mealEntries()->count())->toBe(0);
})->with([
    'unknown recipe version' => [fn ($expectation) => $expectation->andReturn(json_encode([
        'picks' => array_map(fn (int $slot): array => ['slot' => $slot, 'recipe_version_id' => 999999 + $slot], range(0, 4)),
        'note' => '',
    ]))],
    'missing slots' => [fn ($expectation) => $expectation->andReturn(json_encode(['picks' => [], 'note' => '']))],
    'unreadable reply' => [fn ($expectation) => $expectation->andReturn('not json')],
    'request failed' => [fn ($expectation) => $expectation->andThrow(new MealPlanFailed('Claude couldn\'t be reached.'))],
]);

it('marks a planned entry as eaten so it counts and recalculates the coach goal', function () {
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create(['started_on' => '2026-11-16']);
    $entry = MealEntry::factory()->for($user)->pending()->create(['eaten_on' => '2026-11-16']);

    $this->actingAs($user)->post(route('meal-entries.confirm', $entry))->assertInertiaFlash('toast.type', 'success');

    expect($entry->refresh()->status)->toBe(MealEntryStatus::Eaten)
        ->and($user->nutritionSummaryFor(Carbon::parse('2026-11-16'))['totals']['calories'])->toBe($entry->calories)
        ->and($user->goalOn(today())?->source)->toBe(GoalSource::Coach);
});

it('forbids confirming another user\'s entry', function () {
    $entry = MealEntry::factory()->pending()->create();

    $this->actingAs(User::factory()->create())->post(route('meal-entries.confirm', $entry))->assertForbidden();

    expect($entry->refresh()->status)->toBe(MealEntryStatus::Pending);
});

it('marks every planned entry for the day as eaten', function () {
    $user = User::factory()->create();
    $today = MealEntry::factory()->for($user)->pending()->count(2)->create(['eaten_on' => '2026-11-16']);
    $tomorrow = MealEntry::factory()->for($user)->pending()->create(['eaten_on' => '2026-11-17']);
    $someoneElse = MealEntry::factory()->pending()->create(['eaten_on' => '2026-11-16']);

    $this->actingAs($user)->post(route('meal-plans.confirm', '2026-11-16'));

    expect($today->map(fn (MealEntry $entry) => $entry->refresh()->status)->unique()->all())->toBe([MealEntryStatus::Eaten])
        ->and($tomorrow->refresh()->status)->toBe(MealEntryStatus::Pending)
        ->and($someoneElse->refresh()->status)->toBe(MealEntryStatus::Pending);
});

it('discards only the day\'s planned entries', function () {
    $user = User::factory()->create();
    $planned = MealEntry::factory()->for($user)->pending()->create(['eaten_on' => '2026-11-16']);
    $eaten = MealEntry::factory()->for($user)->create(['eaten_on' => '2026-11-16']);
    $someoneElse = MealEntry::factory()->pending()->create(['eaten_on' => '2026-11-16']);

    $this->actingAs($user)->delete(route('meal-plans.destroy', '2026-11-16'));

    expect(MealEntry::find($planned->id))->toBeNull()
        ->and(MealEntry::find($eaten->id))->not->toBeNull()
        ->and(MealEntry::find($someoneElse->id))->not->toBeNull();
});

it('redirects guests to login', function (string $method, string $routeName) {
    $this->{$method}(route($routeName, '2026-11-16'))->assertRedirect(route('login'));
})->with([
    ['post', 'meal-plans.store'],
    ['post', 'meal-plans.confirm'],
    ['delete', 'meal-plans.destroy'],
]);
