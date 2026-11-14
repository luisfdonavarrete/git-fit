<?php

namespace App\Http\Controllers;

use App\Enums\MealType;
use App\Models\MealEntry;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the food diary for one day (today unless `?date=YYYY-MM-DD` is given).
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $date = $this->requestedDate($request);

        $entries = $user->mealEntries()
            ->onDate($date)
            ->with('recipeVersion.recipe:id,image_path')
            ->oldest()
            ->get()
            ->groupBy(fn (MealEntry $entry): string => $entry->meal_type->value);

        return Inertia::render('dashboard', [
            'date' => $date->toDateString(),
            'today' => today()->toDateString(),
            'summary' => $user->nutritionSummaryFor($date),
            'goal' => $user->goalOn(today())?->only('calories', 'protein_g'),
            'meals' => array_map(fn (MealType $type): array => [
                'type' => $type->value,
                'label' => $type === MealType::Snack ? __('Snacks') : $type->label(),
                'entries' => $entries->get($type->value, collect())->map(fn (MealEntry $entry): array => [
                    'id' => $entry->id,
                    'name' => $entry->name,
                    'servings' => $entry->servings,
                    'calorie_range' => $entry->calorie_range->value,
                    'calories' => $entry->calories,
                    'protein_g' => $entry->protein_g,
                    'carbs_g' => $entry->carbs_g,
                    'fat_g' => $entry->fat_g,
                    'image_url' => $entry->recipeVersion?->recipe->image_url,
                ])->values()->all(),
            ], MealType::diarySections()),
        ]);
    }

    /**
     * The diary day from the query string, falling back to today when missing or malformed.
     */
    private function requestedDate(Request $request): CarbonInterface
    {
        $value = $request->string('date')->value();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return today();
        }

        return Date::parse($value)->startOfDay();
    }
}
