<?php

use App\Actions\Coach\SyncCoachGoal;
use App\Enums\GoalSource;
use App\Enums\WeightUnit;
use App\Models\CoachProfile;
use App\Models\NutritionGoal;
use App\Models\User;
use Illuminate\Support\Carbon;

it('saves the targets as the goal for the current coaching week, based on the starting weight until a week has weigh-ins', function () {
    $user = User::factory()->create();
    CoachProfile::factory()->for($user)->create([
        'started_on' => '2026-04-27',
        'starting_weight_kg' => WeightUnit::Lb->toKg(170.8),
        'body_fat_pct' => 24,
    ]);

    $goal = app(SyncCoachGoal::class)->handle($user, Carbon::parse('2026-05-06'));

    expect($goal?->effective_from->toDateString())->toBe('2026-05-04')
        ->and($goal?->source)->toBe(GoalSource::Coach)
        ->and($goal?->maintenance_calories)->toBe(2463)
        ->and($goal?->calories)->toBe(1865)
        ->and($goal?->protein_g)->toBe('136.6')
        ->and($goal?->fat_g)->toBe('45.6')
        ->and($goal?->carbs_g)->toBe('227.1');
});

it('updates the current week\'s goal in place and leaves earlier weeks alone', function () {
    $user = User::factory()->create();
    $profile = CoachProfile::factory()->for($user)->create(['started_on' => '2026-04-27']);
    $earlier = NutritionGoal::factory()->for($user)->create(['effective_from' => '2026-04-27', 'calories' => 2100]);

    app(SyncCoachGoal::class)->handle($user, Carbon::parse('2026-05-05'));
    $profile->update(['body_fat_pct' => 15]);
    app(SyncCoachGoal::class)->handle($user, Carbon::parse('2026-05-07'));

    expect($user->nutritionGoals()->count())->toBe(2)
        ->and($earlier->refresh()->calories)->toBe(2100)
        ->and($user->goalOn(Carbon::parse('2026-05-07'))?->calories)->not->toBe(2100);
});

it('does nothing for users without a coach profile', function () {
    $user = User::factory()->create();

    expect(app(SyncCoachGoal::class)->handle($user))->toBeNull()
        ->and($user->nutritionGoals()->count())->toBe(0);
});
