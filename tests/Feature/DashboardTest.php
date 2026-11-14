<?php

use App\Enums\MealType;
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

test('the dashboard falls back to today for a malformed date', function () {
    $this->travelTo('2026-11-14 09:00');

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard', ['date' => '2026-02-31']));

    $response->assertInertia(fn (Assert $page) => $page->where('date', '2026-11-14'));
});
