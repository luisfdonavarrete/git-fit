<?php

namespace App\Enums;

/**
 * How long the user has trained and dieted consistently; beginners can lean bulk faster.
 */
enum TrainingExperience: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Beginner',
            self::Intermediate => 'Intermediate',
        };
    }

    /**
     * Short explanation shown next to the option.
     */
    public function description(): string
    {
        return match ($this) {
            self::Beginner => 'Less than 1 year of consistent training and dieting',
            self::Intermediate => 'More than 1 year of consistent training and dieting',
        };
    }

    /**
     * Every option with its label and description.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $level): array => [
            'value' => $level->value,
            'label' => $level->label(),
            'description' => $level->description(),
        ], self::cases());
    }
}
