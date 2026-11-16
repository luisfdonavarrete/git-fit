<?php

namespace App\Actions\MealPlans;

/**
 * Picks one recipe version for each open slot of a day.
 */
interface MealPlanner
{
    /**
     * @return list<int> Recipe version ids, one per slot, in slot order.
     *
     * @throws MealPlanFailed When no plan could be made.
     */
    public function plan(MealPlanRequest $request): array;
}
