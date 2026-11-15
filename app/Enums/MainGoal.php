<?php

namespace App\Enums;

/**
 * What the user wants right now; combined with body fat it decides the coaching phase.
 */
enum MainGoal: string
{
    case LoseFat = 'lose_fat';
    case BuildMuscle = 'build_muscle';
    case Maintain = 'maintain';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::LoseFat => 'Lose fat',
            self::BuildMuscle => 'Build muscle',
            self::Maintain => 'Maintain',
        };
    }

    /**
     * Every option with its label.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $goal): array => ['value' => $goal->value, 'label' => $goal->label()], self::cases());
    }
}
