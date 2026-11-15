<?php

namespace App\Models;

use App\Enums\GoalSource;
use Database\Factories\NutritionGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A daily nutrition target that applies from `effective_from` until the user's next goal.
 * Calories and protein are required; the other macros are optional (null means no target).
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $effective_from
 * @property GoalSource $source
 * @property int|null $maintenance_calories
 * @property int $calories
 * @property string $protein_g
 * @property string|null $carbs_g
 * @property string|null $fat_g
 * @property string|null $fiber_g
 * @property string|null $sugar_g
 * @property int|null $sodium_mg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'effective_from',
    'source',
    'maintenance_calories',
    'calories',
    'protein_g',
    'carbs_g',
    'fat_g',
    'fiber_g',
    'sugar_g',
    'sodium_mg',
])]
class NutritionGoal extends Model
{
    /** @use HasFactory<NutritionGoalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'source' => GoalSource::class,
            'maintenance_calories' => 'integer',
            'calories' => 'integer',
            'protein_g' => 'decimal:1',
            'carbs_g' => 'decimal:1',
            'fat_g' => 'decimal:1',
            'fiber_g' => 'decimal:1',
            'sugar_g' => 'decimal:1',
            'sodium_mg' => 'integer',
        ];
    }
}
