<?php

namespace App\Models;

use App\Enums\CalorieRange;
use App\Enums\MealType;
use Carbon\CarbonInterface;
use Database\Factories\MealEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recipe a user ate on a given day. Name, calorie band and macros are snapshotted from the
 * recipe version (already scaled by servings) so later recipe edits never rewrite history.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $recipe_version_id
 * @property Carbon $eaten_on
 * @property MealType $meal_type
 * @property string $servings
 * @property string $name
 * @property CalorieRange $calorie_range
 * @property int $calories
 * @property string|null $protein_g
 * @property string|null $carbs_g
 * @property string|null $fat_g
 * @property string|null $fiber_g
 * @property string|null $sugar_g
 * @property int|null $sodium_mg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['eaten_on', 'meal_type'])]
class MealEntry extends Model
{
    /** @use HasFactory<MealEntryFactory> */
    use HasFactory;

    /**
     * The tracked nutrition columns, shared by entries, goals and the daily summary.
     */
    public const array MACROS = ['calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'sugar_g', 'sodium_mg'];

    /**
     * Macros stored as whole numbers; the rest keep one decimal place.
     */
    public const array INTEGER_MACROS = ['calories', 'sodium_mg'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The recipe version this entry was logged from; null once that recipe is deleted.
     *
     * @return BelongsTo<RecipeVersion, $this>
     */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    /**
     * Point the entry at a recipe version and snapshot its name, band and macros scaled by servings.
     * Unknown (null) macros stay null rather than becoming zero.
     */
    public function fillFromRecipeVersion(RecipeVersion $version, float $servings = 1): static
    {
        $this->recipe_version_id = $version->id;
        $this->servings = (string) $servings;
        $this->name = $version->recipe->name;
        $this->calorie_range = $version->calorie_range;

        foreach (self::MACROS as $macro) {
            $this->setAttribute($macro, self::scale($version->getAttribute($macro), $servings, in_array($macro, self::INTEGER_MACROS, true)));
        }

        return $this;
    }

    /**
     * Change the servings, scaling the entry's own snapshot so later recipe edits still don't leak in.
     */
    public function rescaleTo(float $servings): static
    {
        $previousHundredths = (int) round((float) $this->servings * 100);
        $hundredths = (int) round($servings * 100);

        if ($previousHundredths === $hundredths || $previousHundredths === 0) {
            return $this;
        }

        foreach (self::MACROS as $macro) {
            $value = $this->getAttribute($macro);

            if ($value === null) {
                continue;
            }

            $isInteger = in_array($macro, self::INTEGER_MACROS, true);
            $unitsPerWhole = $isInteger ? 1 : 10;
            $rounded = round((int) round((float) $value * $unitsPerWhole) * $hundredths / $previousHundredths) / $unitsPerWhole;

            $this->setAttribute($macro, $isInteger ? (int) $rounded : $rounded);
        }

        $this->servings = (string) $servings;

        return $this;
    }

    /**
     * Multiply a macro by servings in integer units (tenths × hundredths) so halves round up
     * exactly instead of drifting through float error (e.g. 4.1 × 1.5 = 6.2, not 6.1).
     */
    private static function scale(mixed $value, float $servings, bool $isInteger): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $unitsPerWhole = $isInteger ? 1 : 10;
        $scaled = (int) round((float) $value * $unitsPerWhole) * (int) round($servings * 100);
        $rounded = round($scaled / 100) / $unitsPerWhole;

        return $isInteger ? (int) $rounded : $rounded;
    }

    /**
     * Limit to entries eaten on the given day.
     *
     * @param  Builder<MealEntry>  $query
     */
    public function scopeOnDate(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('eaten_on', $date->toDateString());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eaten_on' => 'date',
            'meal_type' => MealType::class,
            'servings' => 'decimal:2',
            'calorie_range' => CalorieRange::class,
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
