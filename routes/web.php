<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MealEntryController;
use App\Http\Controllers\NutritionGoalController;
use App\Http\Controllers\RecipeSearchController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::resource('meal-entries', MealEntryController::class)->only(['store', 'update', 'destroy']);
    Route::post('nutrition-goal', [NutritionGoalController::class, 'store'])->name('nutrition-goal.store');
    Route::get('recipes/search', RecipeSearchController::class)->name('recipes.search');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
