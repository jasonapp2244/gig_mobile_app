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
            // Update payment_status enum with new values
            $table->enum('payment_status', ['pending', 'paid', 'owed', 'received', 'borrowed', 'return'])
                  ->default('pending')
                  ->change();

            // Add new columns
            $table->string('note')->nullable()->after('payment');
            $table->date('create_date')->nullable()->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_payments', function (Blueprint $table) {
            // Revert enum to original
            $table->enum('payment_status', ['pending', 'paid'])
                  ->default('pending')
                  ->change();

            // Drop added columns
            $table->dropColumn('note');
            $table->dropColumn('create_date');
        });
    }
};
