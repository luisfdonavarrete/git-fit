<?php

namespace App\Enums;

/**
 * The unit a user enters and reads body weight in. Weights are always stored in kilograms.
 */
enum WeightUnit: string
{
    case Lb = 'lb';
    case Kg = 'kg';

    public const float KG_PER_LB = 0.45359237;

    /**
     * Human-readable label, e.g. "lbs".
     */
    public function label(): string
    {
        return match ($this) {
            self::Lb => 'lbs',
            self::Kg => 'kg',
        };
    }

    /**
     * Convert a weight in this unit to kilograms.
     */
    public function toKg(float $weight): float
    {
        return $this === self::Lb ? $weight * self::KG_PER_LB : $weight;
    }

    /**
     * Convert a weight in kilograms to this unit.
     */
    public function fromKg(float $weightKg): float
    {
        return $this === self::Lb ? $weightKg / self::KG_PER_LB : $weightKg;
    }

    /**
     * Every unit with its label.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $unit): array => ['value' => $unit->value, 'label' => $unit->label()], self::cases());
    }
}
