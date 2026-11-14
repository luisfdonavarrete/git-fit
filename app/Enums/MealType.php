<?php

namespace App\Enums;

/**
 * The kinds of meal a recipe is suitable for.
 */
enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';
    case HighProteinSnack = 'high_protein_snack';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'Breakfast',
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
            self::Snack => 'Snack',
            self::HighProteinSnack => 'High-protein snack',
        };
    }

    /**
     * Every meal type with its label, in display order.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
