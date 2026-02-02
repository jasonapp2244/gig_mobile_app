<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function sendToUser(Request $request)
    {
        // Validate request
        $request->validate([
            'title'   => 'required|string',
            'body'    => 'required|string',
            'user_id' => 'required|exists:users,id',
            'task_id' => 'nullable|exists:tasks,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $token = $user->fcm_token;

        if (empty($token)) {
            return response()->json(['message' => 'User does not have a device token.'], 400);
        }

        if (strlen($token) < 50 || strpos($token, ':') === false) {
            return response()->json(['message' => 'Invalid FCM token stored for this user. Please refresh token from device.'], 400);
        }

        // Send notification
        try {
            $this->firebase->sendNotificationToToken(
                $token,
                $request->title,
                $request->body,
                $request->input('data', [])
            );

            // Update task status if task_id is provided
            if ($request->has('task_id')) {
                $task = Task::find($request->task_id);
                if ($task) {
                    $task->update([
                        'is_reminder_sent' => true,
                        'reminder_sent_at' => now()
                    ]);
                }
            }

            return response()->json([
                'message' => 'Notification sent',
                'task_updated' => $request->has('task_id')
            ]);
        } catch (\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
            $user->fcm_token = null;
            $user->save();

            return response()->json([
                'message' => 'The stored FCM token was invalid. It has been cleared. Please refresh token from device.'
            ], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function sendDailyReminders()
    {
        $today = now()->toDateString();

        $tasks = Task::whereDate('task_date_time', $today)
            ->where('is_reminder_sent', false)
            ->orderBy('task_date_time', 'asc')
            ->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($tasks as $task) {
            $user = $task->user;
            if (!$user || !$user->fcm_token) {
                $failedCount++;
                continue;
            }

            try {
                $this->firebase->sendNotificationToToken(
                    $user->fcm_token,
                    "Daily Task Reminder",
                    "Your task '{$task->job_title}' is scheduled for today at {$task->task_date_time->format('H:i')}",
                    [
                        'task_id' => $task->id,
                        'task_date' => $task->task_date_time->toDateString(),
                        'task_time' => $task->task_date_time->format('H:i')
                    ]
                );

                $task->update([
                    'is_reminder_sent' => true,
                    'reminder_sent_at' => now()
                ]);
                $sentCount++;
            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to send reminder for task {$task->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => "Daily reminders processed",
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => $tasks->count()
        ]);
    }

    public function getRemindersStatus()
    {
        $reminders = Task::where('is_reminder_sent', true)
            ->orderBy('reminder_sent_at', 'desc')
            ->get(['id', 'job_title', 'task_date_time', 'is_reminder_sent']);

        return response()->json([
            'status' => true,
            'message' => 'Reminders status fetched successfully',
            'data' => $reminders
        ]);
    }
}
