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
        Schema::table('task_payments', function (Blueprint $table) {
            // Add 'partial' to the payment_status enum
            $table->enum('payment_status', ['pending', 'paid', 'owed', 'received', 'borrowed', 'return', 'partial'])
                  ->default('pending')
                  ->change();

            // Convert payment amount from string to decimal for safe money math
            $table->decimal('payment', 10, 2)->nullable()->change();

            // Accumulated amount paid so far (grows with each partial payment)
            $table->decimal('paid_amount', 10, 2)->default(0)->after('payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_payments', function (Blueprint $table) {
            $table->dropColumn('paid_amount');

            $table->string('payment')->nullable()->change();

            $table->enum('payment_status', ['pending', 'paid', 'owed', 'received', 'borrowed', 'return'])
                  ->default('pending')
                  ->change();
        });
    }
};
