<?php

use App\Enums\GoalSource;
use App\Models\CoachProfile;
use App\Models\DailyCheckIn;
use App\Models\User;

it('records a weigh-in in the coach\'s unit', function () {
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create(['started_on' => '2026-11-09']);

    $this->actingAs($user)->put(route('check-ins.update', '2026-11-14'), ['weight' => 170.8])->assertRedirect();

    $checkIn = $user->dailyCheckIns()->sole();
    expect($checkIn->checked_on->toDateString())->toBe('2026-11-14')
        ->and((float) $checkIn->weight_kg)->toEqualWithDelta(77.47, 0.005)
        ->and($checkIn->calories_override)->toBeNull();
});

it('treats the weight as kilograms for kg users', function () {
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->inKilograms()->create();

    $this->actingAs($user)->put(route('check-ins.update', '2026-11-14'), ['weight' => 77.5]);

    expect($user->dailyCheckIns()->sole()->weight_kg)->toBe('77.50');
});

it('changes only the submitted field', function () {
    $user = User::factory()->create();
    DailyCheckIn::factory()->for($user)->create(['checked_on' => '2026-11-14', 'weight_kg' => 80]);

    $this->actingAs($user)->put(route('check-ins.update', '2026-11-14'), ['calories' => 2300]);

    $checkIn = $user->dailyCheckIns()->sole();
    expect($checkIn->weight_kg)->toBe('80.00')
        ->and($checkIn->calories_override)->toBe(2300);
});

it('removes the check-in when both values are cleared', function () {
    $user = User::factory()->create();
    $checkIn = DailyCheckIn::factory()->for($user)->create(['checked_on' => '2026-11-14', 'weight_kg' => 80]);

    $this->actingAs($user)->put(route('check-ins.update', '2026-11-14'), ['weight' => '']);

    $this->assertModelMissing($checkIn);
});

it('recalculates the coach goal after a check-in', function () {
    $this->travelTo('2026-11-14 09:00');
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create(['started_on' => '2026-11-09']);

    $this->actingAs($user)->put(route('check-ins.update', '2026-11-14'), ['weight' => 170]);

    expect($user->goalOn(today())?->source)->toBe(GoalSource::Coach);
});

it('rejects impossible dates and values', function (string $date, array $input, ?string $field) {
    $response = $this->actingAs(User::factory()->create())->put(route('check-ins.update', $date), $input);

    $field === null ? $response->assertNotFound() : $response->assertSessionHasErrors($field);
})->with([
    'nonexistent day' => ['2026-02-31', ['weight' => 170], null],
    'weight out of range' => ['2026-11-14', ['weight' => 5], 'weight'],
    'negative calories' => ['2026-11-14', ['calories' => -10], 'calories'],
]);
