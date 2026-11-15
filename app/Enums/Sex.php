<?php

namespace App\Enums;

/**
 * Biological sex, which changes the coach's activity multiplier, body-fat brackets and macro splits.
 */
enum Sex: string
{
    case Male = 'male';
    case Female = 'female';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }

    /**
     * Every option with its label.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $sex): array => ['value' => $sex->value, 'label' => $sex->label()], self::cases());
    }
}
