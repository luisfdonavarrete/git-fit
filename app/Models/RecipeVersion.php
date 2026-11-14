<?php

namespace App\Models;

use App\Enums\CalorieRange;
use Database\Factories\RecipeVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $recipe_id
 * @property CalorieRange $calorie_range
 * @property int $calories
 * @property string|null $protein_g
 * @property string|null $carbs_g
 * @property string|null $fat_g
 * @property string|null $fiber_g
 * @property string|null $sugar_g
 * @property int|null $sodium_mg
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'calorie_range',
    'calories',
    'protein_g',
    'carbs_g',
    'fat_g',
    'fiber_g',
    'sugar_g',
    'sodium_mg',
    'notes',
])]
class RecipeVersion extends Model
{
    /** @use HasFactory<RecipeVersionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return HasMany<RecipeIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    /**
     * The directions, in the order they should be followed.
     *
     * @return HasMany<RecipeStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('position');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
