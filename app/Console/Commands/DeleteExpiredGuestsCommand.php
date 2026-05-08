<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteExpiredGuestsCommand extends Command
{
    protected $signature   = 'guests:delete-expired';
    protected $description = 'Delete guest accounts that have passed their 24-hour expiry';

    public function handle()
    {
        // Delete all tokens of expired guests first (Sanctum tokens)
        $expiredGuests = User::where('is_guest', true)
            ->where('guest_expires_at', '<=', now())
            ->get();

        $count = $expiredGuests->count();

        foreach ($expiredGuests as $guest) {
            $guest->tokens()->delete(); // revoke all sanctum tokens
            $guest->forceDelete();      // permanently delete (bypass soft delete)
        }

        $this->info("Deleted {$count} expired guest account(s).");
        Log::info("guests:delete-expired → {$count} expired guest(s) deleted.");

        return Command::SUCCESS;
    }
}
