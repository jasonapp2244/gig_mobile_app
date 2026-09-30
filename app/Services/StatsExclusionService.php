<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves which users are hidden from admin statistics (internal team and
 * test accounts), based on the rules in config/admin_stats.php.
 */
class StatsExclusionService
{
    public const CACHE_KEY = 'admin-stats-excluded-users';

    /** @return int[] */
    public function userIds(): array
    {
        return $this->resolve()['ids'];
    }

    /** @return string[] lower-cased emails of hidden users (for tables keyed by email) */
    public function emails(): array
    {
        return $this->resolve()['emails'];
    }

    public function isExcluded(User $user): bool
    {
        return in_array($user->id, $this->userIds(), true);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{ids: int[], emails: string[]} */
    protected function resolve(): array
    {
        $minutes = max(1, (int) config('admin_stats.cache_minutes', 10));

        return Cache::remember(self::CACHE_KEY, now()->addMinutes($minutes), function () {
            $emails    = array_map('strtolower', (array) config('admin_stats.excluded_emails', []));
            $timezones = (array) config('admin_stats.excluded_timezones', []);
            $pattern   = config('admin_stats.excluded_phone_pattern');

            $ids = [];
            $matchedEmails = [];

            User::select('id', 'email', 'timezone', 'phone_number')
                ->chunkById(500, function ($users) use ($emails, $timezones, $pattern, &$ids, &$matchedEmails) {
                    foreach ($users as $user) {
                        $email = strtolower((string) $user->email);
                        $digits = preg_replace('/\D/', '', (string) $user->phone_number);

                        $hidden = in_array($email, $emails, true)
                            || in_array($user->timezone, $timezones, true)
                            || ($pattern && $digits !== '' && preg_match($pattern, $digits));

                        if ($hidden) {
                            $ids[] = (int) $user->id;
                            if ($email !== '') {
                                $matchedEmails[] = $email;
                            }
                        }
                    }
                });

            return ['ids' => $ids, 'emails' => array_values(array_unique($matchedEmails))];
        });
    }
}
