<?php

namespace App\Http\Controllers;

use App\Http\Requests\NutritionGoalRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class NutritionGoalController extends Controller
{
    /**
     * Set the user's daily targets starting today; earlier days keep the goal they had.
     */
    public function store(NutritionGoalRequest $request): RedirectResponse
    {
        $request->user()->nutritionGoals()->updateOrCreate(
            ['effective_from' => $request->user()->localToday()],
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Daily goal updated.')]);

        return back();
    }
}
