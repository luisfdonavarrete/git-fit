<?php

namespace Database\Factories;

use App\Models\DailyCheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyCheckIn>
 */
class DailyCheckInFactory extends Factory
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
            'checked_on' => today(),
            'weight_kg' => fake()->randomFloat(1, 60, 100),
            'calories_override' => null,
        ];
    }

    /**
     * Also replace the day's diary total with a typed calorie count.
     */
    public function withCaloriesOverride(int $calories): static
    {
        return $this->state(fn (array $attributes) => ['calories_override' => $calories]);
    }
}
