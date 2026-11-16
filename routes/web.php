<?php

use App\Http\Controllers\CoachProfileController;
use App\Http\Controllers\DailyCheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MealEntryController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\NutritionGoalController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\RecipeSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::resource('meal-entries', MealEntryController::class)->only(['show', 'store', 'update', 'destroy']);
    Route::post('meal-entries/{meal_entry}/confirm', [MealEntryController::class, 'confirm'])->name('meal-entries.confirm');
    Route::post('meal-entries/{meal_entry}/swap', [MealPlanController::class, 'swap'])->name('meal-entries.swap');

    Route::controller(MealPlanController::class)
        ->prefix('meal-plans/{date}')
        ->where(['date' => '\d{4}-\d{2}-\d{2}'])
        ->name('meal-plans.')
        ->group(function () {
            Route::post('/', 'store')->name('store');
            Route::post('confirm', 'confirm')->name('confirm');
            Route::delete('/', 'destroy')->name('destroy');
        });
    Route::post('nutrition-goal', [NutritionGoalController::class, 'store'])->name('nutrition-goal.store');
    Route::get('recipes/search', RecipeSearchController::class)->name('recipes.search');

    Route::get('coach', [CoachProfileController::class, 'edit'])->name('coach.edit');
    Route::put('coach', [CoachProfileController::class, 'update'])->name('coach.update');
    Route::put('check-ins/{date}', [DailyCheckInController::class, 'update'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('check-ins.update');
    Route::get('progress', [ProgressController::class, 'show'])->name('progress');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
