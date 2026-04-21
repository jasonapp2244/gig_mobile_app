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
    Schema::create('task_payments', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')
              ->nullable()
              ->constrained()
              ->onDelete('cascade');

        $table->foreignId('task_id')
              ->nullable()
              ->constrained()
              ->onDelete('cascade');

        $table->string('payment_title')->nullable();
        $table->string('payment')->nullable();

        $table->enum('payment_status', ['pending', 'paid'])
              ->default('pending');

        $table->timestamps();
    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_payments');
    }
};
