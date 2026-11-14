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
        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('calorie_range');
            $table->unsignedSmallInteger('calories');
            $table->decimal('protein_g', 6, 1)->nullable();
            $table->decimal('carbs_g', 6, 1)->nullable();
            $table->decimal('fat_g', 6, 1)->nullable();
            $table->decimal('fiber_g', 6, 1)->nullable();
            $table->decimal('sugar_g', 6, 1)->nullable();
            $table->unsignedInteger('sodium_mg')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['recipe_id', 'calorie_range']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_versions');
    }
};
