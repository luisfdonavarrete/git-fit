<?php

use App\Enums\WeightUnit;
use App\Models\CoachProfile;
use App\Models\DailyCheckIn;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('sends users without a coach to the setup page', function () {
    $this->actingAs(User::factory()->create())->get(route('progress'))->assertRedirect(route('coach.edit'));
});

it('lists each week\'s averages and change in the user\'s unit', function () {
    $this->travelTo('2026-11-18 09:00');
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create([
        'started_on' => '2026-11-09',
        'starting_weight_kg' => WeightUnit::Lb->toKg(170),
    ]);
    DailyCheckIn::factory()->for($user)->withCaloriesOverride(1800)->create(['checked_on' => '2026-11-10', 'weight_kg' => WeightUnit::Lb->toKg(169)]);
    DailyCheckIn::factory()->for($user)->withCaloriesOverride(2000)->create(['checked_on' => '2026-11-12', 'weight_kg' => WeightUnit::Lb->toKg(168)]);

    $response = $this->actingAs($user)->get(route('progress'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('progress')
        ->where('unit', 'lbs')
        ->has('weeks', 2)
        ->where('weeks.0.avg_weight', 168.5)
        ->where('weeks.0.change', -1.5)
        ->where('weeks.0.avg_calories', 1900)
        ->where('weeks.0.calorie_days', 2)
        ->where('weeks.1.is_current', true)
        ->where('weeks.1.avg_weight', null)
        ->where('months.0.label', 'Nov 2026')
        ->where('months.0.actual_change', -1.5)
    );
});
