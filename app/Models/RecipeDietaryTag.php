<?php

namespace App\Models;

use App\Enums\DietaryTag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $recipe_id
 * @property DietaryTag $dietary_tag
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['dietary_tag'])]
class RecipeDietaryTag extends Model
{
    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dietary_tag' => DietaryTag::class,
        ];
    }
}
