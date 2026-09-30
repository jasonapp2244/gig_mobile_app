<?php

namespace App\Console\Commands;

use App\Models\IncomeTrackerActivity;
use App\Models\TaskPayment;
use Illuminate\Console\Command;

class BackfillIncomeTrackerActivityCommand extends Command
{
    protected $signature = 'income-tracker:backfill';

    protected $description = 'Rebuild income tracker activity from existing payment records (safe to run more than once)';

    public function handle(): int
    {
        $created = 0;
        $statusChanges = 0;

        // Manual income entries -> "created" at their original time.
        TaskPayment::whereNull('task_id')
            ->whereNotNull('user_id')
            ->chunkById(200, function ($payments) use (&$created) {
                foreach ($payments as $payment) {
                    $exists = IncomeTrackerActivity::where('task_payment_id', $payment->id)
                        ->where('action', IncomeTrackerActivity::ACTION_CREATED)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    IncomeTrackerActivity::create([
                        'user_id'         => $payment->user_id,
                        'task_payment_id' => $payment->id,
                        'action'          => IncomeTrackerActivity::ACTION_CREATED,
                        'payment_title'   => $payment->payment_title,
                        'amount'          => $payment->payment,
                        'new_status'      => $payment->payment_status,
                        'backfilled'      => true,
                        'created_at'      => $payment->created_at,
                    ]);
                    $created++;
                }
            });

        // Work-history rows always start as 'pending'; any other status means the
        // user changed it in the income tracker. Only the latest change is known.
        TaskPayment::whereNotNull('task_id')
            ->whereNotNull('user_id')
            ->where('payment_status', '!=', 'pending')
            ->chunkById(200, function ($payments) use (&$statusChanges) {
                foreach ($payments as $payment) {
                    $exists = IncomeTrackerActivity::where('task_payment_id', $payment->id)
                        ->where('backfilled', true)
                        ->where('action', IncomeTrackerActivity::ACTION_STATUS_CHANGED)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    IncomeTrackerActivity::create([
                        'user_id'         => $payment->user_id,
                        'task_payment_id' => $payment->id,
                        'action'          => IncomeTrackerActivity::ACTION_STATUS_CHANGED,
                        'payment_title'   => $payment->payment_title,
                        'amount'          => $payment->payment,
                        'old_status'      => 'pending',
                        'new_status'      => $payment->payment_status,
                        'backfilled'      => true,
                        'created_at'      => $payment->updated_at ?? $payment->created_at,
                    ]);
                    $statusChanges++;
                }
            });

        $this->info("Backfilled {$created} created entries and {$statusChanges} status changes.");

        return self::SUCCESS;
    }
}
