<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeTrackerActivity extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_VIEWED          = 'viewed';
    public const ACTION_CREATED         = 'created';
    public const ACTION_STATUS_CHANGED  = 'status_changed';
    public const ACTION_PARTIAL_PAYMENT = 'partial_payment';
    public const ACTION_UPDATED         = 'updated';
    public const ACTION_DELETED         = 'deleted';

    /** Human labels for the app screens that count as "opening the income tracker". */
    public const SCREENS = [
        'earning_summary' => 'Earnings summary',
        'payment_history' => 'Payment history',
        'pending_list'    => 'Pending payments',
        'status_list'     => 'Payments by status',
    ];

    protected $fillable = [
        'user_id',
        'task_payment_id',
        'action',
        'screen',
        'payment_title',
        'amount',
        'old_status',
        'new_status',
        'backfilled',
        'created_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'backfilled' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * One-line, plain-text description of the activity for the admin panel.
     */
    public function getDescriptionAttribute(): string
    {
        $title  = $this->payment_title ? '"' . $this->payment_title . '"' : 'an entry';
        $amount = $this->amount !== null ? '$' . number_format((float) $this->amount, 2) : null;

        return match ($this->action) {
            self::ACTION_VIEWED          => 'Opened income tracker (' . (self::SCREENS[$this->screen] ?? 'Income tracker') . ')',
            self::ACTION_CREATED         => 'Added income entry ' . $title . ($amount ? ' — ' . $amount : ''),
            self::ACTION_STATUS_CHANGED  => 'Marked ' . $title . ' as ' . $this->new_status
                                            . ($this->old_status ? ' (was ' . $this->old_status . ')' : ''),
            self::ACTION_PARTIAL_PAYMENT => 'Recorded partial payment ' . ($amount ?? '') . ' on ' . $title
                                            . ($this->new_status ? ' (now ' . $this->new_status . ')' : ''),
            self::ACTION_UPDATED         => 'Updated ' . $title,
            self::ACTION_DELETED         => 'Deleted income entry ' . $title . ($amount ? ' (' . $amount . ')' : ''),
            default                      => ucfirst(str_replace('_', ' ', (string) $this->action)),
        };
    }
}
