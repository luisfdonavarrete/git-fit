<?php

namespace App\Enums;

/**
 * Dietary labels a recipe can carry.
 */
enum DietaryTag: string
{
    case Vegan = 'vegan';
    case Vegetarian = 'vegetarian';
    case GlutenFree = 'gluten_free';
    case DairyFree = 'dairy_free';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Vegan => 'Vegan',
            self::Vegetarian => 'Vegetarian',
            self::GlutenFree => 'Gluten-free',
            self::DairyFree => 'Dairy-free',
        };
    }

    /**
     * Tags that satisfy this one when filtering; a vegan recipe is also vegetarian and dairy-free.
     *
     * @return list<self>
     */
    public function satisfiedBy(): array
    {
        return match ($this) {
            self::Vegetarian, self::DairyFree => [$this, self::Vegan],
            default => [$this],
        };
    }

    /**
     * Every dietary tag with its label, in display order.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $tag): array => ['value' => $tag->value, 'label' => $tag->label()],
            self::cases(),
        );
    }
}
