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
        Schema::table('nutrition_goals', function (Blueprint $table) {
            $table->string('source', 16)->default('manual')->after('effective_from');
            $table->unsignedSmallInteger('maintenance_calories')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nutrition_goals', function (Blueprint $table) {
            $table->dropColumn(['source', 'maintenance_calories']);
        });
    }
};
