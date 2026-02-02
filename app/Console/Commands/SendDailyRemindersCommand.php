<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Task;
use Illuminate\Console\Command;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SendDailyRemindersCommand extends Command
{
    // Artisan command signature
    protected $signature = 'reminders:daily-send';

    // Command description
    protected $description = 'Send daily reminders for all tasks based on each user timezone';

    public function handle()
    {
        // Check if the users table has the 'notifications_enabled' column
        $notificationsEnabledColumnExists = Schema::hasColumn('users', 'notifications_enabled');

        // Get all tasks that haven't had a reminder sent yet, ordered by datetime
        $tasks = Task::where('is_reminder_sent', false)
            ->orderBy('task_date_time', 'asc')
            ->with('user') // eager load user to prevent N+1 queries
            ->get();

        // If no tasks found, log and exit
        if ($tasks->isEmpty()) {
            $this->info('No tasks pending reminders.');
            return Command::SUCCESS;
        }

        $sentCount = 0;
        $failedCount = 0;

        // Resolve FirebaseService from container
        $firebase = app(FirebaseService::class);

        foreach ($tasks as $task) {
            $user = $task->user;

            // Skip if user is missing or FCM token is empty
            if (!$user || !$user->fcm_token) {
                $failedCount++;
                Log::warning("Skipped task {$task->id}: No user or missing FCM token");
                continue;
            }

            // Skip if notifications are disabled for user
            if (
                $notificationsEnabledColumnExists &&
                ($user->notifications_enabled === 0 || $user->notifications_enabled === false)
            ) {
                $failedCount++;
                Log::info("Skipped task {$task->id} for user {$user->id}: Notifications disabled");
                continue;
            }

            try {
                // Get task datetime in UTC (from DB)
                $taskDateTimeUTC = Carbon::parse($task->task_date_time, 'UTC');

                // Get user timezone (or fallback to app timezone)
                $userTz = $user->timezone ?? config('app.timezone');

                // Convert task time to user's local timezone
                $taskDateTimeLocal = $taskDateTimeUTC->copy()->setTimezone($userTz);

                // Get current time in user's timezone
                $nowUser = Carbon::now($userTz);

                // ✅ Send reminder if task date is today in user's timezone
                if ($taskDateTimeLocal->isSameDay($nowUser)) {
                    // Send push notification via Firebase
                    $firebase->sendNotificationToToken(
                        $user->fcm_token,
                        "Daily Task Reminder",
                        "📌 Employer: {$task->employer}\n" .
                            "📌 Task: {$task->job_title}\n" .
                            "🗓 Date: " . $taskDateTimeLocal->format('d M Y') . "\n" .
                            "⏰ Time: " . $taskDateTimeLocal->format('h:i A'),
                        [
                            'task_id'   => $task->id,
                            'job_title' => $task->job_title,
                            'employer'  => $task->employer,
                            'task_date' => $taskDateTimeLocal->toDateString(),
                            'task_time' => $taskDateTimeLocal->format('H:i'),
                            'user_tz'   => $userTz,
                        ]
                    );

                    // Mark task as reminder sent
                    $task->update([
                        'is_reminder_sent' => true,
                        'reminder_sent_at' => now('UTC'), // Save as UTC
                    ]);

                    $sentCount++;
                } else {
                    // Task is not due today in user's timezone
                    $failedCount++;
                    Log::info("Skipped task {$task->id}: Not today for user {$user->id} (tz {$userTz}, task {$taskDateTimeLocal})");
                }
            } catch (\Throwable $e) {
                // Catch and log any errors
                $failedCount++;
                Log::error("Failed to send reminder for task {$task->id}: " . $e->getMessage());
            }
        }

        // Final logging and output
        $this->info("Daily reminders processed: {$sentCount} sent, {$failedCount} skipped/failed out of {$tasks->count()} total");
        Log::info("Daily reminders processed: {$sentCount} sent, {$failedCount} skipped/failed out of {$tasks->count()} total");

        return Command::SUCCESS;
    }
}
