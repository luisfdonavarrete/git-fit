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
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('weight_unit', 8);
            $table->string('sex', 16);
            $table->string('training_experience', 16);
            $table->string('main_goal', 16);
            $table->date('started_on');
            $table->decimal('starting_weight_kg', 5, 2);
            $table->decimal('body_fat_pct', 4, 1);
            $table->decimal('weekly_change_override_kg', 5, 3)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_profiles');
    }
};
