<?php

namespace Database\Seeders;

use App\Actions\Coach\SyncCoachGoal;
use App\Enums\MainGoal;
use App\Enums\MealType;
use App\Enums\Sex;
use App\Enums\TrainingExperience;
use App\Enums\WeightUnit;
use App\Models\RecipeVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Gives the test user about 4½ weeks of coaching history: a profile, daily weigh-ins trending down,
 * and diary meals from the recipe catalog, with a few skipped days and one typed calorie total.
 *
 * Safe to re-run: check-ins are upserted and meals are only added to days that have none.
 * Needs recipes, so run RecipeSeeder first.
 */
class CoachDemoSeeder extends Seeder
{
    /**
     * Days since the coaching start date, so today falls in week 5 (adaptive targets active).
     */
    private const int DAYS_OF_HISTORY = 32;

    /**
     * Rough calories per diary section on a typical day (~1850 kcal total).
     *
     * @var array<string, int>
     */
    private const array SECTION_CALORIES = [
        'breakfast' => 450,
        'lunch' => 550,
        'dinner' => 650,
        'snack' => 200,
    ];

    public function __construct(private SyncCoachGoal $syncCoachGoal) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $versionsBySection = $this->versionsBySection();

        if ($versionsBySection->flatten()->isEmpty()) {
            $this->command?->warn('Skipping coach demo: no recipes. Run the RecipeSeeder first.');

            return;
        }

        $user = User::where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $startedOn = today()->subDays(self::DAYS_OF_HISTORY);
        $startingWeightLb = 170.8;

        $user->coachProfile()->updateOrCreate([], [
            'weight_unit' => WeightUnit::Lb,
            'sex' => Sex::Male,
            'training_experience' => TrainingExperience::Beginner,
            'main_goal' => MainGoal::LoseFat,
            'started_on' => $startedOn,
            'starting_weight_kg' => round(WeightUnit::Lb->toKg($startingWeightLb), 2),
            'body_fat_pct' => 24,
            'weekly_change_override_kg' => null,
        ]);

        mt_srand(170);

        for ($day = 0; $day <= self::DAYS_OF_HISTORY; $day++) {
            $date = $startedOn->copy()->addDays($day);
            $isToday = $day === self::DAYS_OF_HISTORY;

            $this->seedWeighIn($user, $date, $startingWeightLb, $day);
            $this->seedMeals($user, $date, $versionsBySection, $day, $isToday);
        }

        $user->unsetRelation('coachProfile');

        for ($week = 1; $week <= intdiv(self::DAYS_OF_HISTORY, 7) + 1; $week++) {
            $this->syncCoachGoal->handle($user, $startedOn->copy()->addDays(7 * ($week - 1)));
        }

        $this->command?->info("Coach demo ready for {$user->email} (started {$startedOn->toFormattedDateString()}).");
    }

    /**
     * About 0.17 lb/day of loss with ±0.8 lb of day-to-day water noise; every ninth day is skipped.
     */
    private function seedWeighIn(User $user, CarbonInterface $date, float $startingWeightLb, int $day): void
    {
        if ($day > 0 && $day % 9 === 4) {
            return;
        }

        $noise = (mt_rand(-80, 80) / 100) + ($day < 4 ? 0.6 : 0);
        $weightLb = $day === 0 ? $startingWeightLb : $startingWeightLb - 0.17 * $day + $noise;

        $user->dailyCheckIns()->updateOrCreate(
            ['checked_on' => $date],
            ['weight_kg' => round(WeightUnit::Lb->toKg($weightLb), 2)],
        );
    }

    /**
     * A full day of meals, except one unlogged day a week, a restaurant day with a typed total,
     * and today, which only has breakfast and lunch so far.
     *
     * @param  Collection<string, Collection<int, RecipeVersion>>  $versionsBySection
     */
    private function seedMeals(User $user, CarbonInterface $date, Collection $versionsBySection, int $day, bool $isToday): void
    {
        if ($day % 7 === 5) {
            return;
        }

        if ($user->mealEntries()->onDate($date)->exists()) {
            return;
        }

        $sections = $isToday ? ['breakfast', 'lunch'] : array_keys(self::SECTION_CALORIES);

        foreach ($sections as $section) {
            $version = $this->pickVersion($versionsBySection->get($section, collect()), self::SECTION_CALORIES[$section]);

            if ($version === null) {
                continue;
            }

            $user->mealEntries()
                ->make(['eaten_on' => $date, 'meal_type' => MealType::from($section)])
                ->fillFromRecipeVersion($version, 1)
                ->save();
        }

        if ($day === 12) {
            $user->dailyCheckIns()->updateOrCreate(['checked_on' => $date], ['calories_override' => 2450]);
        }
    }

    /**
     * A random version within ±120 kcal of the target, or the closest one if none fit.
     *
     * @param  Collection<int, RecipeVersion>  $versions
     */
    private function pickVersion(Collection $versions, int $targetCalories): ?RecipeVersion
    {
        if ($versions->isEmpty()) {
            return null;
        }

        $close = $versions->filter(fn (RecipeVersion $version): bool => abs($version->calories - $targetCalories) <= 120)->values();

        if ($close->isNotEmpty()) {
            return $close[mt_rand(0, $close->count() - 1)];
        }

        return $versions->sortBy(fn (RecipeVersion $version): int => abs($version->calories - $targetCalories))->first();
    }

    /**
     * Recipe versions grouped by the diary section their recipe is tagged for.
     *
     * @return Collection<string, Collection<int, RecipeVersion>>
     */
    private function versionsBySection(): Collection
    {
        $versions = RecipeVersion::with(['recipe:id,name', 'recipe.mealTypes'])->get();

        return collect(MealType::diarySections())->mapWithKeys(fn (MealType $section): array => [
            $section->value => $versions->filter(fn (RecipeVersion $version): bool => $version->recipe->mealTypes
                ->contains(fn ($tag): bool => in_array($tag->meal_type, $section->suggestedRecipeTags(), true)))->values()->toBase(),
        ]);
    }
}
