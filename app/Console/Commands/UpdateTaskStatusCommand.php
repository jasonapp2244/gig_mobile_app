<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateTaskStatusCommand extends Command
{
    // Artisan command signature
    protected $signature = 'tasks:update-status';

    // Command description
    protected $description = 'Auto-update task status from incomplete to complete when end time has passed (UTC comparison)';

    public function handle()
    {
        // Current time in UTC — same as how task_end_date_time is stored in DB
        $nowUtc = Carbon::now('UTC');

        // Find all incomplete tasks whose end time has already passed
        // Example: task ended 2026-02-20 07:00:00 UTC → now is 2026-02-20 07:10:00 UTC → complete
        $updatedCount = Task::where('status', 'incomplete')
            ->whereNotNull('task_end_date_time')
            ->where('task_end_date_time', '<=', $nowUtc)
            ->update(['status' => 'complete']);

        $this->info("Task status update complete: {$updatedCount} task(s) marked as complete.");
        Log::info("tasks:update-status → {$updatedCount} task(s) marked complete at {$nowUtc}");

        return Command::SUCCESS;
    }
}

