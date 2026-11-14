<?php

use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\RecipeVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('recipes', RecipeController::class);

    Route::resource('recipes.versions', RecipeVersionController::class)
        ->except(['index', 'show'])
        ->scoped();
});
