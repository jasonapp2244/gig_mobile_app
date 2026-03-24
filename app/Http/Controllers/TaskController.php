<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Employer;
use App\Models\Reminder;
use App\Models\TaskPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;
use PhpParser\Node\NullableType;

class TaskController extends Controller
{


    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $userTz = $user->timezone ?? config('app.timezone', 'UTC');


            $tasks = Task::where('user_id', $user->id)
                ->orderBy('task_date_time', 'desc')
                ->get();

            $tasks->transform(function ($task) use ($userTz) {
                $task->task_date_time = $task->task_date_time
                    ? Carbon::parse($task->task_date_time, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s')
                    : null;

                $task->task_end_date_time = $task->task_end_date_time
                    ? Carbon::parse($task->task_end_date_time, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s')
                    : null;

                // Format OT times if they exist
                $task->ot_start_time = $task->ot_start_time
                    ? Carbon::parse($task->ot_start_time)->format('H:i:s')
                    : null;

                $task->ot_end_time = $task->ot_end_time
                    ? Carbon::parse($task->ot_end_time)->format('H:i:s')
                    : null;

                return $task;
            });

            return response()->json([
                'status' => true,
                'message' => 'Task list fetched successfully.',
                'tasks' => $tasks,
                'total_tasks' => $tasks->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch tasks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function store(Request $request)
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'employer' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'supervisor' => 'nullable|string|max:50',
                'position' => 'nullable|string|max:50',

                // Standard time
                'task_date_time' => 'required|date_format:Y-m-d H:i:s',
                'end_time'       => 'required|date_format:Y-m-d H:i:s|after:task_date_time',
                'st_rate'        => 'nullable|numeric',

                // OT is optional
                'ot_start_time'  => 'nullable|date_format:Y-m-d H:i:s',
                'ot_end_time'    => 'nullable|date_format:Y-m-d H:i:s|after:ot_start_time',
                'ot_rate'        => 'nullable|numeric',

                'note' => 'nullable|string'
            ]);


            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            // Parse OT times — store only the time portion (H:i:s), date context comes from task_date_time
            $ot_start_time = $request->ot_start_time ? Carbon::parse($request->ot_start_time)->format('H:i:s') : null;
            $ot_end_time   = $request->ot_end_time   ? Carbon::parse($request->ot_end_time)->format('H:i:s')   : null;

            $stMinutes = 0;
            $otMinutes = 0;

            if (!empty($request->st_total_hours)) {
                if (strpos($request->st_total_hours, ':') !== false) {
                    list($h, $m) = explode(':', $request->st_total_hours);
                    $stMinutes = ((int)$h * 60) + (int)$m;
                } else {
                    $stMinutes = (int)$request->st_total_hours * 60;
                }
            }
            $stTotal = ($request->st_rate ?? 0) * ($stMinutes / 60);


            if (!empty($request->ot_total_hours)) {
                if (strpos($request->ot_total_hours, ':') !== false) {
                    list($h, $m) = explode(':', $request->ot_total_hours);
                    $otMinutes = ((int)$h * 60) + (int)$m;
                } else {
                    $otMinutes = (int)$request->ot_total_hours * 60;
                }
            }
            $otTotal = ($request->ot_rate ?? 0) * ($otMinutes / 60);

            $totalHours = ($stMinutes + $otMinutes) / 60;
            $grandTotal = $stTotal + $otTotal;

            $userTimezone = $user->timezone ?? 'UTC';

            // Convert user's local time to UTC for storage
            $taskStart = Carbon::createFromFormat('Y-m-d H:i:s', $request->task_date_time, $userTimezone)
                ->setTimezone('UTC');

            $taskEnd = Carbon::createFromFormat('Y-m-d H:i:s', $request->end_time, $userTimezone)
                ->setTimezone('UTC');

            $now = Carbon::now('UTC');


            // Block tasks with a past start date
            if ($taskStart->lessThan($now)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Tasks with a past start date cannot be added. Please select a current or future date and time.',
                ], 422);
            }

            $existingTask = Task::where('user_id', $user->id)
                ->where(function ($query) use ($taskStart, $taskEnd) {
                    $query->where('task_date_time', '<', $taskEnd)
                        ->where('task_end_date_time', '>', $taskStart);
                })
                ->first();

            if ($existingTask) {
                return response()->json([
                    'status' => false,
                    'message' => 'You already have a task during this time. Please choose another time.'
                ]);
            }

            // Status: incomplete until end_time passes, then completed
            if ($now->greaterThanOrEqualTo($taskEnd)) {
                $status = 'completed';
            } else {
                $status = 'incomplete';
            }

            $employerId = null;
            $employerName = null;

            if ($request->filled('employer')) {
                $employerName = trim($request->employer);

                $employer = Employer::firstOrCreate(
                    ['employer_name' => $employerName, 'user_id' => $user->id],
                    ['status' => true]
                );

                $employerId = $employer->id;
            }

            $task = Task::create([
                'user_id' => $user->id,
                'employer_id' => $employerId,
                'employer' => $employerName,
                'location' => $request->location,
                'position' => $request->position,
                'task_date_time' => $taskStart,         // UTC
                'task_end_date_time' => $taskEnd,       // UTC
                'start_time' => $taskStart->format('H:i:s'),
                'end_time' => $taskEnd->format('H:i:s'),
                'st_hours' => $request->st_total_hours,
                'supervisor' => $request->supervisor,
                'st_wages' => $request->st_rate,
                'st_total' => $stTotal,
                'ot_start_time' => $ot_start_time,
                'ot_end_time'   => $ot_end_time,
                'ot_hours' => $request->ot_total_hours,
                'ot_wages' => $request->ot_rate,
                'ot_total' => $otTotal,
                'working_hours' => $totalHours,
                'pay' => $grandTotal,
                'notes' => $request->note,
                'status' => $status,
            ]);

            // Save payment if given
            if ($request->filled('st_total_hours')) {
                TaskPayment::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'task_id' => $task->id,
                    ],
                    [
                        'payment_title' => $request->employer,
                        'payment' => $grandTotal,
                        'payment_status' => 'pending',
                        'create_date'    => date('Y-m-d'),
                    ]
                );
            }

            $taskStartLocal = $taskStart->copy()->setTimezone($userTimezone);
            $taskEndLocal   = $taskEnd->copy()->setTimezone($userTimezone);

            return response()->json([
                'status'  => true,
                'message' => 'Task created successfully.',
                'task'    => [
                    'id'                 => $task->id,
                    'user_id'            => $task->user_id,
                    'employer_id'        => $task->employer_id,
                    'employer'           => $task->employer,
                    'position'           => $task->position,
                    'location'           => $task->location,
                    'supervisor'         => $task->supervisor,
                    'task_date_time'     => $taskStartLocal->format('Y-m-d H:i:s'),  // user timezone
                    'task_end_date_time' => $taskEndLocal->format('Y-m-d H:i:s'),    // user timezone
                    'st_wages'           => $task->st_wages,
                    'st_hours'           => $task->st_hours,
                    'st_total'           => $task->st_total,
                    'ot_start_time'      => $task->ot_start_time,   // H:i:s (same as stored)
                    'ot_end_time'        => $task->ot_end_time,     // H:i:s (same as stored)
                    'ot_hours'           => $task->ot_hours,
                    'ot_wages'           => $task->ot_wages,
                    'ot_total'           => $task->ot_total,
                    'working_hours'      => $task->working_hours,
                    'pay'                => $task->pay,
                    'notes'              => $task->notes,
                    'status'             => $task->status,
                    'timezone'           => $userTimezone,
                    'created_at'         => $task->created_at->setTimezone($userTimezone)->format('Y-m-d H:i:s'),
                    'updated_at'         => $task->updated_at->setTimezone($userTimezone)->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function edit($id)
    {
        try {
            $user = Auth::user();
            $userTimezone = $user->timezone ?? 'UTC';

            $task = Task::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$task) {
                return response()->json([
                    'status' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            // Convert task times to user timezone
            $task->task_date_time = $task->task_date_time
                ? Carbon::parse($task->task_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
                : null;

            $task->task_end_date_time = $task->task_end_date_time
                ? Carbon::parse($task->task_end_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
                : null;

            // OT times — H:i:s (same as stored)
            $task->ot_start_time = $task->ot_start_time
                ? Carbon::parse($task->ot_start_time)->format('H:i:s')
                : null;

            $task->ot_end_time = $task->ot_end_time
                ? Carbon::parse($task->ot_end_time)->format('H:i:s')
                : null;

            return response()->json([
                'status'  => true,
                'message' => 'Task retrieved successfully.',
                'data'    => $task,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'employer'      => 'nullable|string|max:255',
                'location'      => 'nullable|string|max:255',
                'supervisor'    => 'nullable|string|max:50',
                'position'      => 'nullable|string|max:50',
                'ot_start_time' => 'nullable|date_format:Y-m-d H:i:s',
                'ot_end_time'   => 'nullable|date_format:Y-m-d H:i:s|after:ot_start_time',
                'ot_hours'      => 'nullable|string',
                'ot_rate'       => 'nullable|numeric',
                'note'          => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $task = Task::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$task) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            // --- OT Calculation ---
            if ($request->filled('ot_hours') || $request->filled('ot_rate')) {
                $otHoursString = $request->ot_hours ?? $task->ot_hours ?? '0:00';
                if (strpos($otHoursString, ':') === false) {
                    $otHoursString .= ':00';
                }
                list($h, $m) = explode(':', $otHoursString);
                $otMinutes = ((int)$h * 60) + (int)$m;
                $otRate    = $request->ot_rate ?? $task->ot_wages ?? 0;
                $otTotal   = ($otRate / 60) * $otMinutes;
            } else {
                $otHoursString = $task->ot_hours ?? '0:00';
                if (strpos($otHoursString, ':') === false) {
                    $otHoursString .= ':00';
                }
                list($h, $m) = explode(':', $otHoursString);
                $otMinutes = ((int)$h * 60) + (int)$m;
                $otRate    = $task->ot_wages ?? 0;
                $otTotal   = $task->ot_total ?? 0;
            }

            // --- ST hours from DB (not changed by this endpoint) ---
            $stHoursString = $task->st_hours ?? '0:00';
            if (strpos($stHoursString, ':') === false) {
                $stHoursString .= ':00';
            }
            list($sh, $sm) = explode(':', $stHoursString);
            $stMinutes = ((int)$sh * 60) + (int)$sm;

            // --- working_hours = st_hours + ot_hours (recalculated, not cumulative) ---
            $totalMinutes = $stMinutes + $otMinutes;
            $workingHours = sprintf('%02d:%02d', floor($totalMinutes / 60), $totalMinutes % 60);

            // --- Grand total = st_total + ot_total ---
            $grandTotal = ($task->st_total ?? 0) + $otTotal;

            // --- OT times (consistent H:i:s format, same as store) ---
            $ot_start_time = $request->ot_start_time
                ? Carbon::parse($request->ot_start_time)->format('H:i:s')
                : $task->ot_start_time;

            $ot_end_time = $request->ot_end_time
                ? Carbon::parse($request->ot_end_time)->format('H:i:s')
                : $task->ot_end_time;

            // --- Update only the allowed fields ---
            $task->update([
                'employer'      => $request->employer   ?? $task->employer,
                'location'      => $request->location   ?? $task->location,
                'position'      => $request->position   ?? $task->position,
                'supervisor'    => $request->supervisor ?? $task->supervisor,
                'notes'         => $request->note       ?? $task->notes,
                'ot_start_time' => $ot_start_time,
                'ot_end_time'   => $ot_end_time,
                'ot_hours'      => $otHoursString,
                'ot_wages'      => $otRate,
                'ot_total'      => $otTotal,
                'working_hours' => $workingHours,
                'pay'           => $grandTotal,
            ]);

            // --- Update payment record ---
            $payment = TaskPayment::where('task_id', $task->id)
                ->where('user_id', $user->id)
                ->first();

            if ($payment) {
                $payment->update([
                    'payment'        => $grandTotal,
                    'payment_title'  => $task->employer,
                    'payment_status' => $payment->payment_status ?? 'pending',
                    'create_date'    => date('Y-m-d'),
                ]);
            }

            $task = $task->fresh();
            $userTimezone = $user->timezone ?? 'UTC';

            // Convert task times to user timezone for response (same as all other endpoints)
            $task->task_date_time = $task->task_date_time
                ? Carbon::parse($task->task_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
                : null;

            $task->task_end_date_time = $task->task_end_date_time
                ? Carbon::parse($task->task_end_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
                : null;

            // OT times — already stored as H:i:s, just ensure consistent format
            $task->ot_start_time = $task->ot_start_time
                ? Carbon::parse($task->ot_start_time)->format('H:i:s')
                : null;

            $task->ot_end_time = $task->ot_end_time
                ? Carbon::parse($task->ot_end_time)->format('H:i:s')
                : null;

            return response()->json([
                'status'  => true,
                'message' => 'Task updated successfully.',
                'task'    => $task,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function filterByStatus(Request $request)
    {
        if ($blocked = $this->blockGuest()) return $blocked;
        try {
            $user = Auth::user();
            $userId = $user->id;

            // User timezone
            $userTz = $user->timezone ?? config('app.timezone');

            $query = Task::where('user_id', $userId);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            } else {
                $query->whereIn('status', ['incomplete', 'completed']);
            }

            // 👉 Latest first: order by date desc + id desc
            $tasks = $query->orderBy('task_date_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            // Update status based on UTC
            $nowUtc = Carbon::now('UTC');

            foreach ($tasks as $task) {
                $endUtc = $task->task_end_date_time ? Carbon::parse($task->task_end_date_time, 'UTC') : null;

                if ($endUtc) {
                    if ($nowUtc->greaterThanOrEqualTo($endUtc) && $task->status !== 'completed') {
                        $task->update(['status' => 'completed']);
                    } elseif ($nowUtc->lessThan($endUtc) && $task->status !== 'incomplete') {
                        $task->update(['status' => 'incomplete']);
                    }
                }
            }

            // Refresh tasks after status update
            if ($request->filled('status')) {
                $tasks = Task::where('user_id', $userId)
                    ->where('status', $request->status)
                    ->orderBy('task_date_time', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                $tasks = Task::where('user_id', $userId)
                    ->whereIn('status', ['incomplete', 'completed'])
                    ->orderBy('task_date_time', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
            }

            // Convert times to user timezone
            $tasks->transform(function ($task) use ($userTz) {
                if ($task->task_date_time) {
                    $task->task_date_time = Carbon::parse($task->task_date_time, 'UTC')
                        ->setTimezone($userTz)
                        ->format('Y-m-d H:i:s');
                }
                if ($task->task_end_date_time) {
                    $task->task_end_date_time = Carbon::parse($task->task_end_date_time, 'UTC')
                        ->setTimezone($userTz)
                        ->format('Y-m-d H:i:s');
                }
                // Format OT times if they exist
                $task->ot_start_time = $task->ot_start_time
                    ? Carbon::parse($task->ot_start_time)->format('H:i:s')
                    : null;
                $task->ot_end_time = $task->ot_end_time
                    ? Carbon::parse($task->ot_end_time)->format('H:i:s')
                    : null;
                return $task;
            });

            // Employer All Summary
            $allTasks = Task::with('employerRelation')
                ->where('user_id', $userId)
                ->whereIn('status', ['incomplete', 'completed'])
                ->get();

            $total = $allTasks->count();

            $employerAllSummary = $allTasks->groupBy('employer_id')->map(function ($tasks, $employerId) use ($userTz) {
                $total = $tasks->count();
                $complete = $tasks->where('status', 'completed')->count();
                $incomplete = $tasks->where('status', 'incomplete')->count();
                $percentage = $total > 0 ? round(($complete / $total) * 100, 2) : 0;

                $employerName = optional($tasks->first())->employer ?? 'N/A';

                // Convert UTC dates to user timezone for correct from/to date
                $dates = $tasks->pluck('task_date_time')->filter()->map(fn($d) => Carbon::parse($d, 'UTC')->setTimezone($userTz));
                return [
                    'employer_id' => $employerId,
                    'employer_name' => $employerName,
                    'total' => $total,
                    'complete' => $complete,
                    'incomplete' => $incomplete,
                    'percentage' => $percentage,
                    'summary_text' => "complete {$complete} / incomplete {$incomplete} / total {$total} / percentage ({$percentage}%)",
                    'from_date' => $dates->min()?->toDateString(),
                    'to_date' => $dates->max()?->toDateString(),
                ];
            })->values();


            //   $userTimezone = auth()->user()->timezone ?? 'UTC';
            //   $dates = $tasks->pluck('task_date_time')
            //         ->filter()
            //         ->map(fn($d) => Carbon::parse($d)->timezone($userTimezone));


            $dates = $tasks->pluck('task_date_time')->filter()->map(fn($d) => Carbon::parse($d));
            //$userTimezone = auth()->user()->timezone ?? 'UTC';

            // Employer Status Summary (if status selected)
            $employerStatusSummary = [];
            if ($request->filled('status')) {
                $status = $request->status;
                $statusFilteredTasks = $allTasks->where('status', $status);
                $allEmployerTasks = $allTasks->groupBy('employer_id');

                $employerStatusSummary = $statusFilteredTasks->groupBy('employer_id')->map(function ($tasks, $employerId) use ($status, $allEmployerTasks, $userTz) {
                    $statusCount = $tasks->count();
                    $totalCount = $allEmployerTasks[$employerId]->count();
                    $employerName = optional($tasks->first())->employer ?? 'N/A';
                    // Convert UTC dates to user timezone for correct from/to date
                    $dates = $tasks->pluck('task_date_time')->filter()->map(fn($d) => Carbon::parse($d, 'UTC')->setTimezone($userTz));
                    $percentage = $totalCount > 0 ? round(($statusCount / $totalCount) * 100, 2) : 0;
                    return [
                        'employer_id' => $employerId,
                        'employer_name' => $employerName,
                        'status' => $status,
                        'count' => $statusCount,
                        'total' => $totalCount,
                        'summary_text' => "{$statusCount}/{$totalCount}",
                        'percentage' => "{$percentage}%",
                        'from_date' => $dates->min()?->toDateString(),
                        'to_date' => $dates->max()?->toDateString(),
                    ];
                })->values();
            }

            return response()->json([
                'status' => true,
                'message' => 'Task list fetched successfully.',
                'tasks' => $tasks,
                'total_tasks' => $total,
                'employer_all_summary' => $employerAllSummary,
                'employer_status_summary' => $employerStatusSummary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public $employerRelation;
    protected $appends = ['employer'];

    public function getEmployerAttribute()
    {
        return $this->employerRelation ? $this->employerRelation->employer_name : null;
    }

    public function markCompleted($id)
    {
        if ($blocked = $this->blockGuest()) return $blocked;
        try {
            $user = Auth::user();
            $userTimezone = $user->timezone ?? 'UTC';

            $task = Task::where('id', $id)->where('user_id', $user->id)->first();
            if (!$task) {
                return response()->json(['status' => false, 'message' => 'Task not found.']);
            }

            $task->status = 'completed';
            $task->save();

            // Convert task times to user timezone
            $task->task_date_time = $task->task_date_time
                ? Carbon::parse($task->task_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
                : null;

            $task->task_end_date_time = $task->task_end_date_time
                ? Carbon::parse($task->task_end_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
                : null;

            // OT times — H:i:s (same as stored)
            $task->ot_start_time = $task->ot_start_time
                ? Carbon::parse($task->ot_start_time)->format('H:i:s')
                : null;

            $task->ot_end_time = $task->ot_end_time
                ? Carbon::parse($task->ot_end_time)->format('H:i:s')
                : null;

            return response()->json(['status' => true, 'message' => 'Task marked as completed.', 'task' => $task]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Delete task method
    public function deleteTask($id)
    {
        if ($blocked = $this->blockGuest()) return $blocked;
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.'
            ], 400);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.'
        ], 200);
    }

    public function filterByEmployerTasks(Request $request)
    {
        if ($blocked = $this->blockGuest()) return $blocked;
        try {
            $user = Auth::user();
            $userId = $user->id;
            $employerId = $request->input('employer_id');

            if (!$employerId || !$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer ID is required.'
                ], 422);
            }

            $taskExists = Task::where('user_id', $userId)
                ->where('employer_id', $employerId)
                ->exists();

            if (!$taskExists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid employer.'
                ], 403);
            }

            // User timezone (fallback app timezone)
            $userTimezone = $user->timezone ?? config('app.timezone');

            $query = Task::where('employer_id', $employerId)
                ->where('user_id', $userId);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            } else {
                $query->whereIn('status', ['incomplete', 'completed']);
            }

            // 👉 Dual ordering: latest task on top
            $tasks = $query->orderBy('task_date_time', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            foreach ($tasks as $task) {
                $now = Carbon::now($userTimezone);

                // Convert DB UTC to user timezone
                $start = $task->task_date_time
                    ? Carbon::parse($task->task_date_time, 'UTC')->setTimezone($userTimezone)
                    : null;

                $end = $task->task_end_date_time
                    ? Carbon::parse($task->task_end_date_time, 'UTC')->setTimezone($userTimezone)
                    : null;

                if ($end) {
                    if ($now->greaterThanOrEqualTo($end) && $task->status !== 'completed') {
                        $task->status = 'completed';
                        $task->save();
                    } elseif ($now->lessThan($end) && $task->status !== 'incomplete') {
                        $task->status = 'incomplete';
                        $task->save();
                    }
                }

                // Response timezone ke hisaab se
                $task->task_date_time = $start ? $start->format('Y-m-d H:i:s') : null;
                $task->task_end_date_time = $end ? $end->format('Y-m-d H:i:s') : null;

                // Format OT times if they exist
                $task->ot_start_time = $task->ot_start_time
                    ? Carbon::parse($task->ot_start_time)->format('H:i:s')
                    : null;
                $task->ot_end_time = $task->ot_end_time
                    ? Carbon::parse($task->ot_end_time)->format('H:i:s')
                    : null;
            }

            // Employer ka summary
            $allTasks = Task::with(['employerRelation', 'employeeRelation'])
                ->where('employer_id', $employerId)
                ->where('user_id', $userId)
                ->whereIn('status', ['incomplete', 'completed'])
                ->get();

            $total = $allTasks->count();
            $complete = $allTasks->where('status', 'completed')->count();
            $incomplete = $allTasks->where('status', 'incomplete')->count();
            $percentage = $total > 0 ? round(($complete / $total) * 100, 2) : 0;

            $dates = $allTasks->pluck('task_date_time')
                ->filter()
                ->map(fn($d) => Carbon::parse($d, 'UTC')->setTimezone($userTimezone));

            $employeeSummary = [
                'employee_id' => $userId,
                'employee_name' => optional($allTasks->first())->employee ?? 'N/A',
                'total' => $total,
                'complete' => $complete,
                'incomplete' => $incomplete,
                'percentage' => $percentage,
                'summary_text' => "{$complete} complete / {$incomplete} incomplete / {$total} total ({$percentage}%)",
                'from_date' => $dates->min()?->toDateString(),
                'to_date' => $dates->max()?->toDateString(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Tasks fetched successfully.',
                'tasks' => $tasks,
                'total_tasks' => $total,
                'employee_summary' => $employeeSummary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showTask($id)
    {
        if ($blocked = $this->blockGuest()) return $blocked;
        $user = Auth::user();
        $userTimezone = $user->timezone ?? config('app.timezone');

        $task = Task::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.'
            ], 400);
        }

        // ✅ Convert times according to user timezone
        $task->task_date_time = $task->task_date_time
            ? Carbon::parse($task->task_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
            : null;

        $task->task_end_date_time = $task->task_end_date_time
            ? Carbon::parse($task->task_end_date_time, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s')
            : null;

        // Format OT times if they exist
        $task->ot_start_time = $task->ot_start_time
            ? Carbon::parse($task->ot_start_time)->format('H:i:s')
            : null;
        $task->ot_end_time = $task->ot_end_time
            ? Carbon::parse($task->ot_end_time)->format('H:i:s')
            : null;

        return response()->json([
            'status' => true,
            'message' => 'Task fetched successfully.',
            'task' => $task
        ]);
    }

    public function tasksByDate(Request $request)
    {
        if ($blocked = $this->blockGuest()) return $blocked;
        try {
            $user = auth()->user();
            $userTz = $user->timezone ?? config('app.timezone', 'UTC');

            // 👉 Date filter optional
            $date = $request->input('date');

            $query = Task::where('user_id', $user->id);

            if ($date) {
                // Convert user's local date to UTC range for correct filtering
                // e.g. user in UTC-5 searching "2025-03-15" → filter UTC 2025-03-15 05:00 to 2025-03-16 04:59
                $startUtc = Carbon::createFromFormat('Y-m-d', $date, $userTz)->startOfDay()->setTimezone('UTC');
                $endUtc   = Carbon::createFromFormat('Y-m-d', $date, $userTz)->endOfDay()->setTimezone('UTC');
                $query->whereBetween('task_date_time', [$startUtc, $endUtc]);
            }

            // 👉 Always latest top pe
            $tasks = $query->orderBy('task_date_time', 'desc')->get();

            // 👉 Timezone conversion
            $tasks->transform(function ($task) use ($userTz) {
                $task->task_date_time = $task->task_date_time
                    ? Carbon::parse($task->task_date_time, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s')
                    : null;

                $task->task_end_date_time = $task->task_end_date_time
                    ? Carbon::parse($task->task_end_date_time, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s')
                    : null;

                // Format OT times if they exist
                $task->ot_start_time = $task->ot_start_time
                    ? Carbon::parse($task->ot_start_time)->format('H:i:s')
                    : null;
                $task->ot_end_time = $task->ot_end_time
                    ? Carbon::parse($task->ot_end_time)->format('H:i:s')
                    : null;

                return $task;
            });
            //if not task
            if($tasks->isEmpty()){
                return response()->json([
                    "status"=>false,
                    "message"=>"No tasks found for the given date",
                    "tasks"=>[],
                    "total_tasks"=>0,
                ],400);
            }

            return response()->json([
                'status' => true,
                'message' => 'Tasks fetched successfully.',
                'tasks' => $tasks,
                'total_tasks' => $tasks->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch tasks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
