<?php

namespace App\Models;

use Database\Factories\DailyCheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One day's weigh-in and, optionally, a typed calorie total that replaces the diary total for that day.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $checked_on
 * @property string|null $weight_kg
 * @property int|null $calories_override
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['checked_on', 'weight_kg', 'calories_override'])]
class DailyCheckIn extends Model
{
    /** @use HasFactory<DailyCheckInFactory> */
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
            'checked_on' => 'date',
            'weight_kg' => 'decimal:2',
            'calories_override' => 'integer',
        ];
    }
}
