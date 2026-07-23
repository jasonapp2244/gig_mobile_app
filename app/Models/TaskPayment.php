<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskPayment extends Model
{
    protected $fillable = [
        'user_id',
        'task_id',
        'payment_title',
        'payment',
        'paid_amount',
        'note',
        'create_date',
        'payment_status',
    ];

    protected $casts = [
        'payment'     => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected $appends = ['remaining'];

    /** Statuses that mean a record is fully settled and can no longer be topped up. */
    public const SETTLED_STATUSES = ['paid', 'received', 'return'];

    /**
     * Amount still outstanding = total payment - amount paid so far.
     */
    public function getRemainingAttribute(): string
    {
        $remaining = (float) $this->payment - (float) $this->paid_amount;

        return number_format(max($remaining, 0), 2, '.', '');
    }

    /**
     * Apply a partial payment (top-up) to this record.
     *
     * Adds $amount to paid_amount, flips to a settled status once the total is
     * reached, otherwise sets 'partial'. Shared by the app API and the admin panel.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public function applyPayment(float $amount, ?string $settleStatus = null): array
    {
        if (in_array($this->payment_status, self::SETTLED_STATUSES, true)) {
            return ['ok' => false, 'code' => 422, 'message' => 'This payment is already settled and cannot be updated.'];
        }

        if ($amount <= 0) {
            return ['ok' => false, 'code' => 422, 'message' => 'Amount must be greater than zero.'];
        }

        $newPaid = (float) $this->paid_amount + $amount;

        if ($newPaid > (float) $this->payment) {
            $remaining = max((float) $this->payment - (float) $this->paid_amount, 0);
            return ['ok' => false, 'code' => 422, 'message' => 'Amount exceeds the remaining balance of ' . number_format($remaining, 2, '.', '')];
        }

        $this->paid_amount = $newPaid;

        // Fully paid -> settle (honour a settle status if given, else 'paid').
        // Otherwise it stays 'partial' and remains in the pending list.
        $this->payment_status = $newPaid >= (float) $this->payment
            ? (in_array($settleStatus, self::SETTLED_STATUSES, true) ? $settleStatus : 'paid')
            : 'partial';

        $this->save();

        return ['ok' => true, 'code' => 200, 'message' => 'Record saved successfully'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
