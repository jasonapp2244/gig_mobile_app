<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskPayment;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class TaskPaymentController extends Controller
{


    public function getTasks()
    {

        $tasks_payments = TaskPayment::where('user_id', Auth::id())
            ->whereIn('payment_status', ['pending', 'owed', 'borrowed'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($tasks_payments->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No pending tasks found',
            ], 400);
        }
        return response()->json([
            'status' => true,
            'message' => 'Pending tasks fetched successfully',
            'data' => $tasks_payments,
        ], 200);
    }

    public function getTasksByStatus($task_status)
    {
        $tasks = TaskPayment::where('user_id', Auth::id())
            ->where('payment_status', $task_status)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($tasks->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No tasks found for the given status',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tasks fetched successfully',
            'data' => $tasks,
        ], 200);
    }

    public function taskPaymentHistory()
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $task_payment_history = TaskPayment::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if ($task_payment_history->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No tasks found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully',
            'data' => $task_payment_history,
        ], 200);
    }



    public function taskPayment(Request $request)
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $isUpdate = $request->has('id');
        if ($isUpdate) {
            // Update case
            $request->validate([
                'id' => 'required|exists:task_payments,id',
                'payment_status' => 'nullable|in:pending,paid,owed,borrowed,received,return',
            ]);

            $task_payment = TaskPayment::findOrFail($request->id);
            $task_payment->update([
                'payment_status' => $request->payment_status,
                'create_date'=>$request->date,
            ]);
        } else {
            $request->validate([
                'payment_title' => 'required|string',
                'payment' => 'required|numeric',
                'payment_status' => 'nullable|in:pending,paid,owed,borrowed,received,return',
            ]);

            $task_payment = TaskPayment::create([
                'user_id' => Auth::id(),
                // 'task_id' => $request->task_id,
                'payment_title' => $request->payment_title,
                'payment' => $request->payment,
                'note' => $request->note,
                'create_date' => $request->date,
                'payment_status' => $request->payment_status ?? 'paid',
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Record saved successfully',
            'task_payment' => $task_payment,
        ], 200);
    }

  
      public function deleteTaskPayment($id)
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $payment = TaskPayment::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$payment) {
            return response()->json([
                'status'  => false,
                'message' => 'Payment record not found',
            ], 404);
        }

        $payment->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Payment record deleted successfully',
        ], 200);
    }
  
    public function earningSummary(Request $request)
    {
        $userId = Auth::id();


        $totalPaid = TaskPayment::where('user_id', $userId)
            ->whereIn('payment_status', ['paid', 'recived'])
            ->sum('payment');

        $pendingEarning = TaskPayment::where('user_id', $userId)
            ->whereIn('payment_status', ['pending', 'owed'])
            ->sum('payment');

        $netEarning = TaskPayment::where('user_id', $userId)
            ->whereIn('payment_status', ['paid', 'recived', 'borrowed'])
            ->sum('payment');

        return response()->json([
            'status' => true,
            'message' => 'Earning summary fetched successfully',
            'available_earning' => $totalPaid,
            'pending_earning' => $pendingEarning,
            'net_earning' => $netEarning,
        ], 200);
    }
}
