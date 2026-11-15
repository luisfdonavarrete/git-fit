<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->nullable()->constrained()->nullOnDelete();
            $table->date('eaten_on');
            $table->string('meal_type', 32);
            $table->decimal('servings', 5, 2)->default(1);
            $table->string('name');
            $table->unsignedSmallInteger('calorie_range');
            $table->unsignedSmallInteger('calories');
            $table->decimal('protein_g', 7, 1)->nullable();
            $table->decimal('carbs_g', 7, 1)->nullable();
            $table->decimal('fat_g', 7, 1)->nullable();
            $table->decimal('fiber_g', 7, 1)->nullable();
            $table->decimal('sugar_g', 7, 1)->nullable();
            $table->unsignedInteger('sodium_mg')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'eaten_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_entries');
    }
};
