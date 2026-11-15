<?php

namespace App\Enums;

/**
 * The phase the coach recommends: a calorie deficit, maintenance, or a small surplus.
 */
enum CoachPhase: string
{
    case Cut = 'cut';
    case Maintain = 'maintain';
    case LeanBulk = 'lean_bulk';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cut => 'Cut (calorie deficit)',
            self::Maintain => 'Maintain',
            self::LeanBulk => 'Lean bulk (calorie surplus)',
        };
    }
}
