<?php

namespace App\Enums;

/**
 * Calorie bands a recipe version can target, keyed by the band's lower bound.
 */
enum CalorieRange: int
{
    case Under200 = 0;
    case From200 = 200;
    case From300 = 300;
    case From400 = 400;
    case From500 = 500;
    case From600 = 600;
    case From700 = 700;
    case Over800 = 800;

    /**
     * Human-readable label, e.g. "Under 200", "200–300" or "800+".
     */
    public function label(): string
    {
        return match ($this) {
            self::Under200 => 'Under 200',
            self::Over800 => '800+',
            default => $this->value.'–'.($this->value + 100),
        };
    }
}
