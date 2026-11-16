<?php

use App\Actions\Coach\CalculateCoachTargets;
use App\Enums\CoachPhase;
use App\Enums\MainGoal;
use App\Enums\Sex;
use App\Enums\TrainingExperience;
use App\Enums\WeightUnit;
use App\Models\CoachProfile;
use Illuminate\Support\Carbon;

/**
 * A coaching week as BuildCoachWeeks returns it.
 *
 * @return array{number: int, starts_on: string, ends_on: string, avg_weight_kg: float|null, weigh_in_days: int, avg_calories: float|null, calorie_days: int, measured_tdee: float|null}
 */
function coachWeek(int $number, ?float $averageWeightLb = null, ?float $measuredTdee = null): array
{
    $startsOn = Carbon::parse('2026-04-27')->addDays(7 * ($number - 1));

    return [
        'number' => $number,
        'starts_on' => $startsOn->toDateString(),
        'ends_on' => $startsOn->copy()->addDays(6)->toDateString(),
        'avg_weight_kg' => $averageWeightLb === null ? null : WeightUnit::Lb->toKg($averageWeightLb),
        'weigh_in_days' => $averageWeightLb === null ? 0 : 7,
        'avg_calories' => null,
        'calorie_days' => 0,
        'measured_tdee' => $measuredTdee,
    ];
}

/**
 * Luis's spreadsheet inputs: 170.8 lb, 24% body fat, male beginner wanting to lose fat, starting Mon 27-Apr-26.
 *
 * @param  array<string, mixed>  $overrides
 */
function sheetProfile(array $overrides = []): CoachProfile
{
    return CoachProfile::factory()->make([
        'weight_unit' => WeightUnit::Lb,
        'sex' => Sex::Male,
        'training_experience' => TrainingExperience::Beginner,
        'main_goal' => MainGoal::LoseFat,
        'started_on' => '2026-04-27',
        'starting_weight_kg' => WeightUnit::Lb->toKg(170.8),
        'body_fat_pct' => 24,
        ...$overrides,
    ]);
}

it('reproduces the spreadsheet targets before the adaptive switch', function () {
    $weeks = [coachWeek(1, 167.54), coachWeek(2, 166.76), coachWeek(3, 166.5)];

    $targets = (new CalculateCoachTargets)->handle(sheetProfile(), $weeks, Carbon::parse('2026-05-13'));

    expect($targets)->toMatchArray([
        'current_week' => 3,
        'phase' => CoachPhase::Cut,
        'weekly_change_pct' => -0.7,
        'maintenance' => 2463,
        'maintenance_source' => 'formula',
        'calories' => 1879,
        'calorie_range' => [1800, 2000],
        'protein_g' => 136.6,
        'protein_range' => [125, 171],
        'fat_g' => 45.9,
        'fat_range' => [30, 60],
        'carbs_g' => 229.9,
        'carbs_range' => [180, 280],
        'adapts_from' => '2026-05-18',
    ])
        ->and(WeightUnit::Lb->fromKg($targets['weekly_change_kg']))->toEqualWithDelta(-1.167, 0.001);
});

it('switches to the average measured maintenance of complete weeks from week 4, skipping week 1', function () {
    $weeks = [
        coachWeek(1, 167.54, measuredTdee: 3403),
        coachWeek(2, 166.4, measuredTdee: 2628),
        coachWeek(3, 166.66, measuredTdee: 1700),
        coachWeek(4, 166.0, measuredTdee: 1500),
    ];

    $targets = (new CalculateCoachTargets)->handle(sheetProfile(), $weeks, Carbon::parse('2026-05-18'));

    expect($targets['maintenance_source'])->toBe('measured')
        ->and($targets['maintenance'])->toBe(2164)
        ->and($targets['calories'])->toBe(1581)
        ->and($targets['calorie_range'])->toBe([1600, 1700]);
});

it('keeps the formula maintenance from week 4 when no complete week has a measured value', function () {
    $weeks = [coachWeek(1, 167.54), coachWeek(2, 166.4), coachWeek(3, 166.66), coachWeek(4)];

    $targets = (new CalculateCoachTargets)->handle(sheetProfile(), $weeks, Carbon::parse('2026-05-20'));

    expect($targets['maintenance_source'])->toBe('formula')
        ->and($targets['maintenance'])->toBe(2463);
});

it('bases the weekly change on the latest complete week with weigh-ins', function () {
    $weeks = [coachWeek(1, 168.0), coachWeek(2), coachWeek(3, 150.0)];

    $targets = (new CalculateCoachTargets)->handle(sheetProfile(), $weeks, Carbon::parse('2026-05-13'));

    expect(WeightUnit::Lb->fromKg($targets['latest_weight_kg']))->toEqualWithDelta(168.0, 0.01);
});

it('picks the phase and weekly rate from goal, sex, body fat and experience', function (array $overrides, CoachPhase $phase, float $ratePct) {
    $targets = (new CalculateCoachTargets)->handle(sheetProfile($overrides), [], Carbon::parse('2026-04-27'));

    expect($targets['phase'])->toBe($phase)
        ->and($targets['weekly_change_pct'])->toBe($ratePct);
})->with([
    'man cutting at 25%' => [['body_fat_pct' => 25], CoachPhase::Cut, -0.7],
    'man cutting at 14%' => [['body_fat_pct' => 14], CoachPhase::Cut, -0.5],
    'man cutting at 12%' => [['body_fat_pct' => 12], CoachPhase::Cut, -0.3],
    'woman cutting at 24%' => [['sex' => Sex::Female, 'body_fat_pct' => 24], CoachPhase::Cut, -0.5],
    'woman cutting at 22%' => [['sex' => Sex::Female, 'body_fat_pct' => 22], CoachPhase::Cut, -0.3],
    'man building muscle at 25% cuts first' => [['main_goal' => MainGoal::BuildMuscle, 'body_fat_pct' => 25], CoachPhase::Cut, -0.7],
    'beginner lean bulk' => [['main_goal' => MainGoal::BuildMuscle, 'body_fat_pct' => 15], CoachPhase::LeanBulk, 0.375],
    'intermediate lean bulk' => [['main_goal' => MainGoal::BuildMuscle, 'body_fat_pct' => 15, 'training_experience' => TrainingExperience::Intermediate], CoachPhase::LeanBulk, 0.25],
    'woman building muscle at 30% cuts first' => [['sex' => Sex::Female, 'main_goal' => MainGoal::BuildMuscle, 'body_fat_pct' => 30], CoachPhase::Cut, -0.7],
    'maintain' => [['main_goal' => MainGoal::Maintain], CoachPhase::Maintain, 0.0],
]);

it('uses a manual weekly change instead of the recommended rate', function () {
    $targets = (new CalculateCoachTargets)->handle(
        sheetProfile(['weekly_change_override_kg' => WeightUnit::Lb->toKg(-0.5)]),
        [],
        Carbon::parse('2026-04-27'),
    );

    expect($targets['weekly_change_pct'])->toBeNull()
        ->and($targets['calories'])->toBe(2463 - 250);
});

it('never goes below the calorie floor and shows the range from the target up', function (Sex $sex, int $floor) {
    $targets = (new CalculateCoachTargets)->handle(
        sheetProfile(['sex' => $sex, 'starting_weight_kg' => 45, 'weekly_change_override_kg' => -1]),
        [],
        Carbon::parse('2026-04-27'),
    );

    expect($targets['calories'])->toBe($floor)
        ->and($targets['calorie_range'])->toBe([$floor, $floor + 100]);
})->with([
    'men' => [Sex::Male, 1500],
    'women' => [Sex::Female, 1200],
]);

it('uses the female activity multiplier, protein and fat shares', function () {
    $targets = (new CalculateCoachTargets)->handle(
        sheetProfile(['sex' => Sex::Female, 'main_goal' => MainGoal::Maintain, 'starting_weight_kg' => WeightUnit::Lb->toKg(140), 'body_fat_pct' => 28]),
        [],
        Carbon::parse('2026-04-27'),
    );

    $maintenance = (370 + 9.8 * 140 * 0.72) * 1.55;

    expect($targets['calories'])->toBe((int) round($maintenance))
        ->and($targets['protein_g'])->toBe(round(0.8 * 140, 1))
        ->and($targets['fat_g'])->toBe(round(0.30 * $maintenance / 9, 1));
});

it('explains the targets with the inputs behind each formula', function () {
    $targets = (new CalculateCoachTargets)->handle(sheetProfile(), [coachWeek(1, 167.54), coachWeek(2, 166.76), coachWeek(3, 166.5)], Carbon::parse('2026-05-13'));

    expect($targets['breakdown'])->toMatchArray([
        'body_fat_pct' => 24.0,
        'activity_multiplier' => 1.5,
        'rate_rule' => 'Cutting at 15–25% body fat: -0.7% of body weight per week.',
        'calorie_floor' => 1500,
        'is_floored' => false,
        'protein_per_lb' => [0.8, 0.73, 1.0],
        'fat_share' => [0.22, 0.15, 0.30],
        'measured_weeks' => [],
    ])
        ->and(WeightUnit::Lb->fromKg($targets['breakdown']['lean_mass_kg']))->toEqualWithDelta(129.81, 0.01)
        ->and((int) round($targets['breakdown']['unfloored_calories']))->toBe($targets['calories']);
});

it('lists the measured weeks that make up maintenance', function () {
    $weeks = [
        coachWeek(1, 167.54, measuredTdee: 3403),
        coachWeek(2, 166.4, measuredTdee: 2628),
        coachWeek(3, 166.66, measuredTdee: 1700),
        coachWeek(4, 166.0, measuredTdee: 1500),
    ];

    $measured = (new CalculateCoachTargets)->handle(sheetProfile(), $weeks, Carbon::parse('2026-05-18'))['breakdown']['measured_weeks'];

    expect(array_column($measured, 'number'))->toBe([2, 3])
        ->and(array_column($measured, 'tdee'))->toBe([2628.0, 1700.0])
        ->and(WeightUnit::Lb->fromKg((float) $measured[0]['weight_change_kg']))->toEqualWithDelta(-1.14, 0.001);
});

it('flags a target raised to the calorie floor', function () {
    $targets = (new CalculateCoachTargets)->handle(
        sheetProfile(['starting_weight_kg' => 45, 'weekly_change_override_kg' => -1]),
        [],
        Carbon::parse('2026-04-27'),
    );

    expect($targets['breakdown']['is_floored'])->toBeTrue()
        ->and($targets['breakdown']['unfloored_calories'])->toBeLessThan(1500)
        ->and($targets['breakdown']['rate_rule'])->toBe('You set a custom weekly change.');
});
