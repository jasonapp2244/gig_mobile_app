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
        Schema::table('tasks', function (Blueprint $table) {

            // Standard time fields
            $table->decimal('st_wages', 10, 2)->nullable()->after('travel_pay');
            $table->string('st_hours')->nullable()->after('st_wages');
            $table->decimal('st_total', 10, 2)->nullable()->after('st_hours');

            // Overtime fields
            $table->decimal('ot_wages', 10, 2)->nullable()->after('st_total');
            $table->decimal('ot_total', 10, 2)->nullable()->after('ot_wages');
            $table->Time('ot_start_time')->nullable()->after('ot_total');
            $table->Time('ot_end_time')->nullable()->after('ot_start_time');
            $table->string('ot_hours')->nullable()->after('ot_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'st_wages',
                'st_hours',
                'st_total',
                'ot_wages',
                'ot_total',
                'ot_start_time',
                'ot_end_time',
                'ot_hours',
            ]);
        });
    }
};
