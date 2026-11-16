<?php

namespace App\Enums;

use App\Actions\MealPlans\MealPlanner;
use App\Actions\MealPlans\PlanDayFromRecipes;
use App\Actions\MealPlans\PlanDayWithClaude;

/**
 * The ways a day's meal plan can be generated.
 */
enum MealPlannerType: string
{
    case Search = 'search';
    case Claude = 'claude';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Search => 'Recipe library',
            self::Claude => 'Claude (experimental)',
        };
    }

    /**
     * Whether this planner can be used with the current configuration.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Search => true,
            self::Claude => filled(config('services.anthropic.key')),
        };
    }

    /**
     * The planner class that implements this strategy.
     *
     * @return class-string<MealPlanner>
     */
    public function plannerClass(): string
    {
        return match ($this) {
            self::Search => PlanDayFromRecipes::class,
            self::Claude => PlanDayWithClaude::class,
        };
    }

    /**
     * Every planner with its label and availability, in display order.
     *
     * @return list<array{value: string, label: string, available: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label(), 'available' => $type->isAvailable()],
            self::cases(),
        );
    }
}
