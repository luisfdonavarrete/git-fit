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
        Schema::table('meal_entries', function (Blueprint $table) {
            $table->string('status', 16)->default('eaten')->after('meal_type');

            $table->index(['user_id', 'eaten_on', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_entries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'eaten_on', 'status']);
            $table->dropColumn('status');
        });
    }
};
