<?php

namespace App\Policies;

use App\Models\MealEntry;
use App\Models\User;

class MealEntryPolicy
{
    /**
     * Only the owner can see an entry's recipe.
     */
    public function view(User $user, MealEntry $mealEntry): bool
    {
        return $mealEntry->user_id === $user->id;
    }

    /**
     * Only the owner can change an entry.
     */
    public function update(User $user, MealEntry $mealEntry): bool
    {
        return $mealEntry->user_id === $user->id;
    }

    /**
     * Only the owner can remove an entry.
     */
    public function delete(User $user, MealEntry $mealEntry): bool
    {
        return $mealEntry->user_id === $user->id;
    }
}
