<?php

use App\Enums\MealType;
use App\Enums\WeightUnit;
use App\Models\CoachProfile;
use App\Models\DailyCheckIn;
use App\Models\MealEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows the requested day\'s entries for the user in four diary sections', function () {
    $user = User::factory()->create();
    $day = '2026-11-10';
    $breakfast = MealEntry::factory()->for($user)->create(['eaten_on' => $day, 'meal_type' => MealType::Breakfast]);
    $snack = MealEntry::factory()->for($user)->create(['eaten_on' => $day, 'meal_type' => MealType::Snack]);
    MealEntry::factory()->for($user)->create(['eaten_on' => '2026-11-11', 'meal_type' => MealType::Breakfast]);
    MealEntry::factory()->create(['eaten_on' => $day, 'meal_type' => MealType::Breakfast]);

    $response = $this->actingAs($user)->get(route('dashboard', ['date' => $day]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('date', $day)
        ->where('summary.totals.calories', $breakfast->calories + $snack->calories)
        ->where('meals.0.type', 'breakfast')
        ->has('meals.0.entries', 1)
        ->where('meals.0.entries.0.id', $breakfast->id)
        ->where('meals.1.type', 'lunch')
        ->has('meals.1.entries', 0)
        ->where('meals.2.type', 'dinner')
        ->where('meals.3.type', 'snack')
        ->has('meals.3.entries', 1)
        ->where('meals.3.entries.0.id', $snack->id)
    );
});

test('the dashboard shows planned entries in their section with a plan summary, outside the day\'s totals', function () {
    $user = User::factory()->create();
    $day = '2026-11-10';
    $eaten = MealEntry::factory()->for($user)->create(['eaten_on' => $day, 'meal_type' => MealType::Lunch]);
    $planned = MealEntry::factory()->for($user)->pending()->create(['eaten_on' => $day, 'meal_type' => MealType::Lunch]);

    $response = $this->actingAs($user)->get(route('dashboard', ['date' => $day]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('summary.totals.calories', $eaten->calories)
        ->where('meals.1.entries.0.status', 'eaten')
        ->where('meals.1.entries.1.id', $planned->id)
        ->where('meals.1.entries.1.status', 'pending')
        ->where('mealPlan.pending_count', 1)
        ->where('mealPlan.planned.calories', $planned->calories)
        ->where('mealPlan.planners.0.value', 'search')
    );
});

test('the dashboard falls back to today for a malformed date', function () {
    $this->travelTo('2026-11-14 09:00');

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard', ['date' => '2026-02-31']));

    $response->assertInertia(fn (Assert $page) => $page->where('date', '2026-11-14'));
});

test('the dashboard shows the coach targets, calorie range and the day\'s weigh-in', function () {
    $this->travelTo('2026-11-14 09:00');
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create([
        'started_on' => '2026-11-09',
        'starting_weight_kg' => WeightUnit::Lb->toKg(170.8),
        'body_fat_pct' => 24,
    ]);
    DailyCheckIn::factory()->for($user)->create(['checked_on' => '2026-11-14', 'weight_kg' => WeightUnit::Lb->toKg(168)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('coach.phase.value', 'cut')
        ->where('coach.adapts_from', '2026-11-30')
        ->where('coach.day_calorie_range', [1750, 1950])
        ->where('summary.targets.calories', 1865)
        ->where('checkIn.weight', 168)
        ->where('checkIn.unit', 'lbs')
    );
});

test('the dashboard has no coach data until the coach is set up', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->where('coach', null)->where('checkIn.weight', null));
});

test('today is the user\'s local day, not the UTC day', function (?string $timezone, string $today) {
    $this->travelTo('2026-11-17 01:15:00');
    $user = User::factory()->create(['timezone' => $timezone]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('today', $today)
        ->where('date', $today));
})->with([
    'evening in New York' => ['America/New_York', '2026-11-16'],
    'no timezone yet falls back to UTC' => [null, '2026-11-17'],
]);
