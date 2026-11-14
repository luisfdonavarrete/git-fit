<?php

namespace App\Models;

use App\Enums\MealType;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'image_path'])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /**
     * The calorie versions available for this recipe, lowest band first.
     *
     * @return HasMany<RecipeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class)->orderBy('calorie_range');
    }

    /**
     * The meals this recipe is suitable for.
     *
     * @return HasMany<RecipeMealType, $this>
     */
    public function mealTypes(): HasMany
    {
        return $this->hasMany(RecipeMealType::class);
    }

    /**
     * Replace the recipe's meal types with exactly the given ones.
     *
     * @param  array<array-key, MealType>  $mealTypes
     */
    public function syncMealTypes(array $mealTypes): void
    {
        $values = array_values(array_unique(array_map(fn (MealType $type): string => $type->value, $mealTypes)));

        $this->mealTypes()->whereNotIn('meal_type', $values)->delete();

        $existing = $this->mealTypes()->pluck('meal_type')->map(fn (MealType $type): string => $type->value)->all();

        $this->mealTypes()->createMany(
            array_map(fn (string $value): array => ['meal_type' => $value], array_values(array_diff($values, $existing))),
        );
    }

    /**
     * Limit to recipes tagged with the given meal type.
     *
     * @param  Builder<Recipe>  $query
     */
    public function scopeForMealType(Builder $query, MealType $mealType): void
    {
        $query->whereHas('mealTypes', fn (Builder $mealTypes) => $mealTypes->where('meal_type', $mealType));
    }
}
