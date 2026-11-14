<?php

namespace App\Http\Controllers;

use App\Models\IncomeTrackerActivity;
use App\Models\TaskPayment;
use App\Services\IncomeTrackerActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Income tracker APIs for the app:
 *  - GET  /api/income-tracker/list         the user's records, newest entry date first
 *  - POST /api/income-tracker/update/{id}  edit any field of the user's own record
 *
 * Both responses carry status_options for the app's status dropdown.
 */
class IncomeTrackerController extends Controller
{
    /** Allowed payment_status values (the DB enum) with the label to show. */
    private const STATUSES = [
        'pending'  => 'Pending',
        'paid'     => 'Paid',
        'owed'     => 'Owed',
        'borrowed' => 'Borrowed',
        'received' => 'Received',
        'return'   => 'Returned',
        'partial'  => 'Partially paid',
    ];

    public function __construct(protected IncomeTrackerActivityService $activity)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $request->validate([
            'status'   => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = min((int) $request->input('per_page', 10), 50);

        $records = TaskPayment::where('user_id', Auth::id())
            ->when($request->filled('status'), fn ($q) => $q->where('payment_status', $request->input('status')))
            // entry date first; records without one use the day they were added
            ->orderByRaw('COALESCE(create_date, DATE(created_at)) DESC')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        if ($records->total() === 0) {
            return response()->json([
                'status'         => false,
                'message'        => 'No records found',
                'data'           => [],
                'status_options' => $this->statusOptions(),
            ], 200);
        }

        return response()->json([
            'status'         => true,
            'message'        => 'Records fetched successfully',
            'data'           => $records,
            'status_options' => $this->statusOptions(),
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $payment = TaskPayment::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $payment) {
            return response()->json([
                'status'  => false,
                'message' => 'Payment record not found',
            ], 404);
        }

        $data = $request->validate([
            'payment_title'  => ['sometimes', 'required', 'string', 'max:255'],
            'payment'        => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'paid_amount'    => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_status' => ['sometimes', 'required', Rule::in(array_keys(self::STATUSES))],
            'note'           => ['sometimes', 'nullable', 'string'],
            'date'           => ['sometimes', 'nullable', 'date'],
        ]);

        $oldStatus = $payment->payment_status;
        $total     = round((float) ($data['payment'] ?? $payment->payment), 2);
        $paid      = round((float) ($data['paid_amount'] ?? $payment->paid_amount), 2);

        if ($paid > $total) {
            return $this->invalid('paid_amount', 'Paid amount cannot be more than the total amount.');
        }

        if (array_key_exists('payment_status', $data)) {
            $status = $data['payment_status'];

            if ($status === 'partial' && ($paid <= 0 || $paid >= $total)) {
                return $this->invalid('paid_amount', 'For a partial payment the paid amount must be more than 0 and less than the total amount.');
            }
        } elseif (
            (array_key_exists('payment', $data) || array_key_exists('paid_amount', $data))
            && in_array($oldStatus, ['pending', 'partial'], true)
        ) {
            // Amounts changed without a chosen status: work it out like the add API does
            $status = match (true) {
                $paid >= $total => 'paid',
                $paid > 0       => 'partial',
                default         => 'pending',
            };
        } else {
            $status = $oldStatus;
        }

        if (array_key_exists('payment_title', $data)) {
            $payment->payment_title = $data['payment_title'];
        }
        if (array_key_exists('note', $data)) {
            $payment->note = $data['note'];
        }
        if (array_key_exists('date', $data)) {
            $payment->create_date = $data['date'];
        }
        $payment->payment        = $total;
        $payment->paid_amount    = $paid;
        $payment->payment_status = $status;
        $payment->save();

        $statusChanged = $oldStatus !== $payment->payment_status;

        $this->activity->logPayment(
            Auth::user(),
            $statusChanged ? IncomeTrackerActivity::ACTION_STATUS_CHANGED : IncomeTrackerActivity::ACTION_UPDATED,
            $payment,
            [
                'old_status' => $oldStatus,
                'new_status' => $payment->payment_status,
            ]
        );

        return response()->json([
            'status'         => true,
            'message'        => 'Record updated successfully',
            'task_payment'   => $payment->fresh(),
            'status_options' => $this->statusOptions(),
        ], 200);
    }

    private function statusOptions(): array
    {
        $options = [];
        foreach (self::STATUSES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /** 422 in the same shape as Laravel's validation errors. */
    private function invalid(string $field, string $message): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'errors'  => [$field => [$message]],
        ], 422);
    }
}
