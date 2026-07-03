<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('employer_id')->nullable()->constrained('employers')->onDelete('cascade');
            $table->string('employer')->nullable();
            $table->string('job_title')->nullable();

            $table->enum('job_type', ['night', 'day', 'off', 'hoot', 'vocation'])
                ->default('day')
                ->nullable();

            $table->enum('job_category', ['hourly', 'monthly', 'yearly'])
                ->default('hourly')
                ->nullable();

            $table->string('location')->nullable();
            $table->string('supervisor_contact_number')->nullable();
            $table->string('working_hours')->nullable();
            $table->string('straight_time')->nullable();

            $table->boolean('make_hole')->default(false);
            $table->decimal('pay', 10, 2)->nullable();
            $table->decimal('guaranteed_steady_hours', 8, 2)->nullable();
            $table->decimal('flop_hours', 8, 2)->nullable();
            $table->decimal('avg_hours', 8, 2)->nullable();
            $table->decimal('bonus_pay', 10, 2)->nullable();

            $table->string('travel_location')->nullable();
            $table->decimal('travel_hours', 8, 2)->nullable();
            $table->decimal('travel_pay', 10, 2)->nullable();

            $table->decimal('wages', 10, 2)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('schedule_date')->nullable();

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->dateTime('task_date_time')->nullable();
            $table->dateTime('task_end_date_time')->nullable();

            $table->boolean('is_reminder_sent')->default(false);
            $table->dateTime('reminder_sent_at')->nullable();

            $table->enum('status', ['pending', 'ongoing', 'completed', 'cancelled','incomplete'])
                ->default('pending');

            $table->text('notes')->nullable();
            $table->boolean('has_entry')->default(false);
            $table->boolean('is_locked')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
}
