<?php

namespace App\Models;

use App\Enums\CalorieRange;
use App\Enums\DietaryTag;
use App\Enums\MealType;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image_path
 * @property-read string|null $image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'image_path'])]
#[Appends(['image_url'])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /**
     * A browser-ready URL for the image: external URLs as-is, stored files via the public disk.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => match (true) {
            blank($this->image_path) => null,
            Str::startsWith($this->image_path, ['http://', 'https://']) => $this->image_path,
            default => Storage::disk('public')->url($this->image_path),
        });
    }

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
     * The dietary labels this recipe carries, e.g. vegan or gluten-free.
     *
     * @return HasMany<RecipeDietaryTag, $this>
     */
    public function dietaryTags(): HasMany
    {
        return $this->hasMany(RecipeDietaryTag::class);
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

    /**
     * Replace the recipe's dietary tags with exactly the given ones.
     *
     * @param  array<array-key, DietaryTag>  $dietaryTags
     */
    public function syncDietaryTags(array $dietaryTags): void
    {
        $values = array_values(array_unique(array_map(fn (DietaryTag $tag): string => $tag->value, $dietaryTags)));

        $this->dietaryTags()->whereNotIn('dietary_tag', $values)->delete();

        $existing = $this->dietaryTags()->pluck('dietary_tag')->map(fn (DietaryTag $tag): string => $tag->value)->all();

        $this->dietaryTags()->createMany(
            array_map(fn (string $value): array => ['dietary_tag' => $value], array_values(array_diff($values, $existing))),
        );
    }

    /**
     * Limit to recipes that meet every given dietary requirement (a vegan recipe counts as vegetarian and dairy-free).
     *
     * @param  Builder<Recipe>  $query
     * @param  array<array-key, DietaryTag>  $dietaryTags
     */
    public function scopeMeetsDiets(Builder $query, array $dietaryTags): void
    {
        foreach ($dietaryTags as $dietaryTag) {
            $query->whereHas('dietaryTags', fn (Builder $tags) => $tags->whereIn('dietary_tag', $dietaryTag->satisfiedBy()));
        }
    }

    /**
     * Limit to recipes that have a version in the given calorie range.
     *
     * @param  Builder<Recipe>  $query
     */
    public function scopeWithVersionInRange(Builder $query, CalorieRange $calorieRange): void
    {
        $query->whereHas('versions', fn (Builder $versions) => $versions->where('calorie_range', $calorieRange));
    }
}
