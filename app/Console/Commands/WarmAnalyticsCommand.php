<?php

namespace App\Console\Commands;

use App\Services\FirebaseAnalyticsService;
use Illuminate\Console\Command;

class WarmAnalyticsCommand extends Command
{
    protected $signature   = 'analytics:warm';
    protected $description = 'Load the Firebase Analytics reports from Google into the cache so the admin page opens instantly';

    public function handle(FirebaseAnalyticsService $analytics): int
    {
        // 28 days first: it is what the page opens with
        foreach ([28, 7, 90] as $days) {
            $report = $analytics->warm($days);

            if (in_array($report['status'], [
                FirebaseAnalyticsService::STATUS_NOT_CONFIGURED,
                FirebaseAnalyticsService::STATUS_API_DISABLED,
                FirebaseAnalyticsService::STATUS_NO_ACCESS,
            ], true)) {
                $this->warn("Firebase Analytics is not set up ({$report['status']}): nothing to warm.");

                return Command::SUCCESS;
            }

            $note = match (true) {
                ! empty($report['partial']) => ' (still loading: ' . implode(', ', $report['partial']) . ')',
                ! empty($report['stale'])   => ' (some sections from the last good copy)',
                default                     => '',
            };
            $this->info("{$days} days: {$report['status']}{$note}");
        }

        return Command::SUCCESS;
    }
}
