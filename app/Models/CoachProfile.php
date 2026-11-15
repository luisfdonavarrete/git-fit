<?php

namespace App\Models;

use App\Enums\MainGoal;
use App\Enums\Sex;
use App\Enums\TrainingExperience;
use App\Enums\WeightUnit;
use Carbon\CarbonInterface;
use Database\Factories\CoachProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The answers the coach bases its targets on. Weights are stored in kg whatever unit the user picked.
 *
 * @property int $id
 * @property int $user_id
 * @property WeightUnit $weight_unit
 * @property Sex $sex
 * @property TrainingExperience $training_experience
 * @property MainGoal $main_goal
 * @property Carbon $started_on
 * @property string $starting_weight_kg
 * @property string $body_fat_pct
 * @property string|null $weekly_change_override_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'weight_unit',
    'sex',
    'training_experience',
    'main_goal',
    'started_on',
    'starting_weight_kg',
    'body_fat_pct',
    'weekly_change_override_kg',
])]
class CoachProfile extends Model
{
    /** @use HasFactory<CoachProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The 1-based coaching week the given day falls in (0 before the start date).
     */
    public function weekNumberOn(CarbonInterface $date): int
    {
        $days = (int) $this->started_on->diffInDays($date->copy()->startOfDay(), false);

        return $days < 0 ? 0 : intdiv($days, 7) + 1;
    }

    /**
     * The first day of the given coaching week.
     */
    public function weekStartsOn(int $weekNumber): CarbonInterface
    {
        return $this->started_on->copy()->addDays(7 * ($weekNumber - 1));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_unit' => WeightUnit::class,
            'sex' => Sex::class,
            'training_experience' => TrainingExperience::class,
            'main_goal' => MainGoal::class,
            'started_on' => 'date',
            'starting_weight_kg' => 'decimal:2',
            'body_fat_pct' => 'decimal:1',
            'weekly_change_override_kg' => 'decimal:3',
        ];
    }
}
