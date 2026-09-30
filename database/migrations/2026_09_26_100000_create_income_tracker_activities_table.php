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
        Schema::create('income_tracker_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            // No FK: the activity must survive when the payment record is deleted.
            $table->unsignedBigInteger('task_payment_id')->nullable()->index();

            // viewed | created | status_changed | partial_payment | updated | deleted
            $table->string('action', 30);

            // Which income tracker screen was opened (only for 'viewed').
            $table->string('screen', 30)->nullable();

            // Snapshot of the payment at the time of the action.
            $table->string('payment_title')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();

            // True for rows rebuilt from existing data before tracking went live.
            $table->boolean('backfilled')->default(false);

            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_tracker_activities');
    }
};
