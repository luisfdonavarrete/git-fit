<?php

use App\Actions\Coach\BuildCoachWeeks;
use App\Actions\Coach\CalculateCoachTargets;
use App\Models\CoachProfile;
use App\Models\DailyCheckIn;
use App\Models\MealEntry;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Support\Carbon;

it('averages only the days that were actually logged', function () {
    $user = User::factory()->create();
    $profile = CoachProfile::factory()->for($user)->create(['started_on' => '2026-04-27']);
    DailyCheckIn::factory()->for($user)->create(['checked_on' => '2026-04-27', 'weight_kg' => 80]);
    DailyCheckIn::factory()->for($user)->create(['checked_on' => '2026-04-29', 'weight_kg' => 79]);

    $weeks = (new BuildCoachWeeks)->handle($profile, Carbon::parse('2026-05-01'));

    expect($weeks)->toHaveCount(1)
        ->and($weeks[0])->toMatchArray([
            'number' => 1,
            'starts_on' => '2026-04-27',
            'ends_on' => '2026-05-03',
            'avg_weight_kg' => 79.5,
            'weigh_in_days' => 2,
            'avg_calories' => null,
            'calorie_days' => 0,
            'measured_tdee' => null,
        ]);
});

it('takes daily calories from eaten diary entries unless a typed total overrides it', function () {
    $user = User::factory()->create();
    $profile = CoachProfile::factory()->for($user)->create(['started_on' => '2026-04-27']);
    $version = RecipeVersion::factory()->create(['calories' => 600]);
    MealEntry::factory()->for($user)->fromRecipeVersion($version)->count(3)->create(['eaten_on' => '2026-04-27']);
    MealEntry::factory()->for($user)->fromRecipeVersion($version)->create(['eaten_on' => '2026-04-28']);
    DailyCheckIn::factory()->for($user)->withCaloriesOverride(2500)->create(['checked_on' => '2026-04-28', 'weight_kg' => null]);
    MealEntry::factory()->fromRecipeVersion($version)->create(['eaten_on' => '2026-04-29']);
    MealEntry::factory()->for($user)->pending()->fromRecipeVersion($version)->create(['eaten_on' => '2026-04-27']);
    MealEntry::factory()->for($user)->pending()->fromRecipeVersion($version)->create(['eaten_on' => '2026-04-30']);

    $week = (new BuildCoachWeeks)->handle($profile, Carbon::parse('2026-05-03'))[0];

    expect($week['calorie_days'])->toBe(2)
        ->and($week['avg_calories'])->toBe(2150.0);
});

it('measures weekly maintenance from intake and the change in average weight', function () {
    $user = User::factory()->create();
    $profile = CoachProfile::factory()->for($user)->create(['started_on' => '2026-04-27']);
    foreach (range(0, 6) as $day) {
        DailyCheckIn::factory()->for($user)->create(['checked_on' => Carbon::parse('2026-04-27')->addDays($day), 'weight_kg' => 80]);
        DailyCheckIn::factory()->for($user)->withCaloriesOverride(2000)->create(['checked_on' => Carbon::parse('2026-05-04')->addDays($day), 'weight_kg' => 79.5]);
    }

    $weeks = (new BuildCoachWeeks)->handle($profile, Carbon::parse('2026-05-11'));

    expect($weeks)->toHaveCount(3)
        ->and($weeks[0]['measured_tdee'])->toBeNull()
        ->and($weeks[1]['measured_tdee'])->toEqualWithDelta(2000 + 0.5 * CalculateCoachTargets::KCAL_PER_KG / 7, 0.001)
        ->and($weeks[2]['measured_tdee'])->toBeNull();
});

it('does not measure a week with fewer than four calorie-logged days', function () {
    $user = User::factory()->create();
    $profile = CoachProfile::factory()->for($user)->create(['started_on' => '2026-04-27']);
    DailyCheckIn::factory()->for($user)->create(['checked_on' => '2026-04-27', 'weight_kg' => 80]);
    foreach (range(0, 2) as $day) {
        DailyCheckIn::factory()->for($user)->withCaloriesOverride(2000)->create(['checked_on' => Carbon::parse('2026-05-04')->addDays($day), 'weight_kg' => 79.5]);
    }

    $weeks = (new BuildCoachWeeks)->handle($profile, Carbon::parse('2026-05-10'));

    expect($weeks[1]['calorie_days'])->toBe(3)
        ->and($weeks[1]['measured_tdee'])->toBeNull();
});

it('returns no weeks before the coaching start date', function () {
    $profile = CoachProfile::factory()->create(['started_on' => '2026-04-27']);

    expect((new BuildCoachWeeks)->handle($profile, Carbon::parse('2026-04-26')))->toBe([]);
});
