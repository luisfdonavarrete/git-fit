<?php

namespace App\Enums;

/**
 * Whether a diary entry is only planned or was actually eaten. Only eaten entries count toward totals.
 */
enum MealEntryStatus: string
{
    case Pending = 'pending';
    case Eaten = 'eaten';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Planned',
            self::Eaten => 'Eaten',
        };
    }
}
