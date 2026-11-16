<?php

namespace App\Http\Controllers;

use App\Actions\Coach\SyncCoachGoal;
use App\Actions\MealPlans\GenerateMealPlan;
use App\Actions\MealPlans\MealPlanFailed;
use App\Enums\MealEntryStatus;
use App\Http\Requests\StoreMealPlanRequest;
use App\Models\MealEntry;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MealPlanController extends Controller
{
    /**
     * Plan the day's open meals as pending entries, replacing any earlier plan for that day.
     */
    public function store(StoreMealPlanRequest $request, string $date, GenerateMealPlan $generateMealPlan): RedirectResponse
    {
        $planner = app($request->plannerType()->plannerClass());

        try {
            $plan = $generateMealPlan->handle($request->user(), $this->day($date), $planner);
        } catch (MealPlanFailed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $message = __('Planned :count meals · :calories kcal.', [
            'count' => $plan['entries']->count(),
            'calories' => number_format((int) $plan['entries']->sum('calories')),
        ]);

        Inertia::flash('toast', $plan['fits_calorie_window']
            ? ['type' => 'success', 'message' => $message]
            : ['type' => 'warning', 'message' => $message.' '.__('No combination fit your calorie range, so this is the closest match.')]);

        return back();
    }

    /**
     * Swap one planned meal for a different recipe, keeping the rest of the day's plan.
     */
    public function swap(StoreMealPlanRequest $request, MealEntry $mealEntry, GenerateMealPlan $generateMealPlan): RedirectResponse
    {
        Gate::authorize('update', $mealEntry);

        $previousName = $mealEntry->name;

        try {
            $swap = $generateMealPlan->swap($mealEntry, app($request->plannerType()->plannerClass()));
        } catch (MealPlanFailed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $message = __('Swapped :previous for :name.', ['previous' => $previousName, 'name' => $swap['entry']->name]);

        Inertia::flash('toast', $swap['fits_calorie_window']
            ? ['type' => 'success', 'message' => $message]
            : ['type' => 'warning', 'message' => $message.' '.__('Nothing fit your calorie range, so this is the closest match.')]);

        return back();
    }

    /**
     * Mark every planned entry for the day as eaten.
     */
    public function confirm(Request $request, string $date, SyncCoachGoal $syncCoachGoal): RedirectResponse
    {
        $confirmed = $request->user()->mealEntries()->pending()->onDate($this->day($date))->update(['status' => MealEntryStatus::Eaten]);
        $syncCoachGoal->handle($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} 1 meal marked as eaten.|[2,*] :count meals marked as eaten.', $confirmed, ['count' => $confirmed])]);

        return back();
    }

    /**
     * Discard the day's planned entries, leaving eaten ones alone.
     */
    public function destroy(Request $request, string $date): RedirectResponse
    {
        $request->user()->mealEntries()->pending()->onDate($this->day($date))->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meal plan discarded.')]);

        return back();
    }

    private function day(string $date): CarbonInterface
    {
        $day = Date::createFromFormat('!Y-m-d', $date);

        abort_if($day === null || $day->toDateString() !== $date, 404);

        return $day;
    }
}
