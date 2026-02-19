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
            $table->string('supervisor')->nullable()->after('location');
            $table->string('position')->nullable()->after('job_title');
            $table->decimal('ot_wages')->nullable()->after('wages');
            $table->string('rate')->nullable()->after('ot_wages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'supervisor',
                'position',
                'ot_wages',
                'rate'
            ]);
        });
    }
};
