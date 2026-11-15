<?php

namespace Database\Factories;

use App\Enums\MainGoal;
use App\Enums\Sex;
use App\Enums\TrainingExperience;
use App\Enums\WeightUnit;
use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachProfile>
 */
class CoachProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weight_unit' => WeightUnit::Lb,
            'sex' => Sex::Male,
            'training_experience' => TrainingExperience::Beginner,
            'main_goal' => MainGoal::LoseFat,
            'started_on' => today(),
            'starting_weight_kg' => fake()->randomFloat(1, 60, 100),
            'body_fat_pct' => fake()->numberBetween(12, 30),
            'weekly_change_override_kg' => null,
        ];
    }

    /**
     * A woman's profile.
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes) => ['sex' => Sex::Female]);
    }

    /**
     * Entered and displayed in kilograms.
     */
    public function inKilograms(): static
    {
        return $this->state(fn (array $attributes) => ['weight_unit' => WeightUnit::Kg]);
    }
}
