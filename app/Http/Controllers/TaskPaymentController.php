<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskPayment;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskPaymentController extends Controller
{


    public function getTasks()
    {

        $tasks_payments = TaskPayment::where('user_id', Auth::id())
            ->whereIn('payment_status', ['pending', 'owed', 'borrowed', 'partial'])
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
                'payment_status' => 'nullable|in:pending,paid,owed,borrowed,received,return,partial',
                'paid_amount' => 'nullable|numeric|min:0.01',
            ]);

            $task_payment = TaskPayment::findOrFail($request->id);

            // A partial payment is being made against this record.
            if ($request->filled('paid_amount')) {
                $result = $task_payment->applyPayment((float) $request->paid_amount, $request->payment_status);

                if (! $result['ok']) {
                    return response()->json([
                        'status'  => false,
                        'message' => $result['message'],
                    ], $result['code']);
                }

                if ($request->filled('date')) {
                    $task_payment->create_date = $request->date;
                    $task_payment->save();
                }
            } else {
                // Plain status / date update (existing behaviour).
                $task_payment->update([
                    'payment_status' => $request->payment_status,
                    'create_date' => $request->date,
                ]);
            }
        } else {
            $request->validate([
                'payment_title' => 'required|string',
                'payment' => 'required|numeric',
                'payment_status' => 'nullable|in:pending,paid,owed,borrowed,received,return,partial',
                'paid_amount' => 'nullable|numeric|min:0|lte:payment',
            ]);

            $paidAmount = (float) ($request->paid_amount ?? 0);
            $status = $request->payment_status ?? 'paid';

            // If a partial amount is supplied on create, derive the status from it.
            if ($request->filled('paid_amount')) {
                $status = $paidAmount >= (float) $request->payment ? 'paid' : 'partial';
            }

            $task_payment = TaskPayment::create([
                'user_id' => Auth::id(),
                // 'task_id' => $request->task_id,
                'payment_title' => $request->payment_title,
                'payment' => $request->payment,
                'paid_amount' => $paidAmount,
                'note' => $request->note,
                'create_date' => $request->date,
                'payment_status' => $status,
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

        // Partial records split by column: paid_amount counts as earned,
        // (payment - paid_amount) counts as pending.
        $partialPaid = TaskPayment::where('user_id', $userId)
            ->where('payment_status', 'partial')
            ->sum('paid_amount');

        $partialRemaining = TaskPayment::where('user_id', $userId)
            ->where('payment_status', 'partial')
            ->sum(DB::raw('payment - paid_amount'));

        $totalPaid      += $partialPaid;
        $netEarning     += $partialPaid;
        $pendingEarning += $partialRemaining;

        return response()->json([
            'status' => true,
            'message' => 'Earning summary fetched successfully',
            'available_earning' => $totalPaid,
            'pending_earning' => $pendingEarning,
            'net_earning' => $netEarning,
        ], 200);
    }
}
