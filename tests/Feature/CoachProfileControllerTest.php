<?php

use App\Enums\GoalSource;
use App\Enums\MainGoal;
use App\Enums\WeightUnit;
use App\Models\CoachProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function coachAnswers(array $overrides = []): array
{
    return [
        'weight_unit' => 'lb',
        'sex' => 'male',
        'training_experience' => 'beginner',
        'main_goal' => 'lose_fat',
        'started_on' => '2026-11-09',
        'starting_weight' => 170.8,
        'body_fat_pct' => 24,
        'weekly_change_override' => null,
        ...$overrides,
    ];
}

it('shows an empty setup for a user without a coach', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('coach.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('coach/edit')
        ->where('profile', null)
        ->where('targets', null)
        ->has('options.mainGoals', 3)
    );
});

it('sets up the coach in kg and writes this week\'s goal', function () {
    $this->travelTo('2026-11-14 09:00');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('coach.update'), coachAnswers([
        'weight_unit' => 'kg',
        'starting_weight' => 77.5,
        'weekly_change_override' => -0.4,
    ]));

    $response->assertRedirect(route('dashboard'));
    $profile = $user->coachProfile()->sole();
    expect($profile->weight_unit)->toBe(WeightUnit::Kg)
        ->and($profile->starting_weight_kg)->toBe('77.50')
        ->and($profile->weekly_change_override_kg)->toBe('-0.400');
    $goal = $user->goalOn(today());
    expect($goal?->source)->toBe(GoalSource::Coach)
        ->and($goal?->effective_from->toDateString())->toBe('2026-11-09');
});

it('converts pounds to kilograms when saving', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('coach.update'), coachAnswers(['starting_weight' => 170.8, 'weekly_change_override' => -1]));

    expect((float) $user->coachProfile()->sole()->starting_weight_kg)->toEqualWithDelta(77.47, 0.005)
        ->and((float) $user->coachProfile()->sole()->weekly_change_override_kg)->toEqualWithDelta(-0.454, 0.0005);
});

it('updates the existing profile and returns to the coach page', function () {
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create();

    $response = $this->actingAs($user)->from(route('coach.edit'))
        ->put(route('coach.update'), coachAnswers(['main_goal' => 'maintain']));

    $response->assertRedirect(route('coach.edit'));
    expect($user->coachProfile()->sole()->main_goal)->toBe(MainGoal::Maintain);
});

it('shows the saved answers in the user\'s unit with the calculated targets', function () {
    $this->travelTo('2026-11-14 09:00');
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('coach.update'), coachAnswers());

    $response = $this->get(route('coach.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('profile.starting_weight', 170.8)
        ->where('targets.phase.value', 'cut')
        ->where('targets.maintenance', 2463)
        ->where('targets.calories', 1865)
        ->where('targets.unit', 'lbs')
    );
});

it('rejects invalid answers', function (array $overrides, string $field) {
    $response = $this->actingAs(User::factory()->create())->put(route('coach.update'), coachAnswers($overrides));

    $response->assertSessionHasErrors($field);
})->with([
    'unknown goal' => [['main_goal' => 'shred'], 'main_goal'],
    'weight too low for pounds' => [['starting_weight' => 50], 'starting_weight'],
    'weight too high for kilograms' => [['weight_unit' => 'kg', 'starting_weight' => 400], 'starting_weight'],
    'body fat out of range' => [['body_fat_pct' => 70], 'body_fat_pct'],
    'weekly change too aggressive' => [['weekly_change_override' => -5], 'weekly_change_override'],
    'malformed start date' => [['started_on' => '11/09/2026'], 'started_on'],
]);
