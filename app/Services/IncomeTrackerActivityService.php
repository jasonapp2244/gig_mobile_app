<?php

namespace App\Services;

use App\Models\IncomeTrackerActivity;
use App\Models\TaskPayment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IncomeTrackerActivityService
{
    public function __construct(protected StatsExclusionService $exclusion)
    {
    }

    // -----------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------

    /**
     * Record one income tracker action. Never throws: a logging failure must
     * not break the app's API response. Guest users are not tracked.
     */
    public function log(?User $user, string $action, array $attributes = []): void
    {
        if (! $user || $user->is_guest) {
            return;
        }

        try {
            IncomeTrackerActivity::create(array_merge($attributes, [
                'user_id' => $user->id,
                'action'  => $action,
            ]));
        } catch (Throwable $e) {
            Log::warning('Income tracker activity log failed: ' . $e->getMessage());
        }
    }

    /** Snapshot a payment record's title and amount for the log. */
    public function logPayment(?User $user, string $action, TaskPayment $payment, array $attributes = []): void
    {
        $this->log($user, $action, array_merge([
            'task_payment_id' => $payment->id,
            'payment_title'   => $payment->payment_title,
            'amount'          => $payment->payment,
        ], $attributes));
    }

    /**
     * Record that the user opened an income tracker screen, at most once per
     * throttle window per user.
     */
    public function logView(?User $user, string $screen): void
    {
        if (! $user || $user->is_guest) {
            return;
        }

        $minutes = max(1, (int) config('income_tracker.view_throttle_minutes', 5));

        try {
            if (! Cache::add('income-tracker-view:' . $user->id, true, now()->addMinutes($minutes))) {
                return;
            }
        } catch (Throwable $e) {
            Log::warning('Income tracker view throttle failed: ' . $e->getMessage());
        }

        $this->log($user, IncomeTrackerActivity::ACTION_VIEWED, ['screen' => $screen]);
    }

    // -----------------------------------------------------------------
    // Reading (admin panel)
    // -----------------------------------------------------------------

    /** Timezone the admin sees (set per admin by SetAdminTimezone). */
    public function adminTimezone(): string
    {
        return config('app.timezone') ?: 'UTC';
    }

    /** Convert a moment in the admin's timezone to the timezone timestamps are stored in. */
    protected function toStorage(CarbonInterface $moment): Carbon
    {
        return Carbon::instance($moment)->setTimezone(date_default_timezone_get());
    }

    /** Activity of users counted in admin stats (internal team / test accounts left out). */
    protected function counted()
    {
        return IncomeTrackerActivity::whereNotIn('user_id', $this->exclusion->userIds());
    }

    /** Active users today / 7 days / 30 days, actions today, and a 30-day daily trend. */
    public function dashboardStats(): array
    {
        $tz  = $this->adminTimezone();
        $now = Carbon::now($tz);

        $todayStart  = $this->toStorage($now->copy()->startOfDay());
        $weekStart   = $this->toStorage($now->copy()->subDays(6)->startOfDay());
        $monthStart  = $this->toStorage($now->copy()->subDays(29)->startOfDay());

        $activeSince = fn (Carbon $from) => $this->counted()->where('created_at', '>=', $from)
            ->distinct()
            ->count('user_id');

        // Group the last 30 days per admin-local day in PHP so day boundaries follow the admin's timezone.
        $rows = $this->counted()->where('created_at', '>=', $monthStart)
            ->get(['user_id', 'created_at']);

        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $days[$now->copy()->subDays($i)->format('Y-m-d')] = ['users' => [], 'actions' => 0];
        }

        foreach ($rows as $row) {
            $day = $row->created_at->copy()->setTimezone($tz)->format('Y-m-d');
            if (isset($days[$day])) {
                $days[$day]['users'][$row->user_id] = true;
                $days[$day]['actions']++;
            }
        }

        return [
            'active_today'  => $activeSince($todayStart),
            'active_7d'     => $activeSince($weekStart),
            'active_30d'    => $activeSince($monthStart),
            'actions_today' => $this->counted()->where('created_at', '>=', $todayStart)->count(),
            'total_users'   => $this->counted()->distinct()->count('user_id'),
            'trend'         => [
                'labels'  => array_map(fn ($d) => Carbon::parse($d)->format('M d'), array_keys($days)),
                'users'   => array_map(fn ($d) => count($d['users']), array_values($days)),
                'actions' => array_map(fn ($d) => $d['actions'], array_values($days)),
            ],
        ];
    }

    /**
     * Latest activities, newest first. With $afterId only rows newer than that
     * id are returned (incremental refresh).
     */
    public function activities(array $filters = [], int $limit = 50): Collection
    {
        $query = IncomeTrackerActivity::with('user:id,name,user_name,email')
            ->orderByDesc('id');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        } else {
            // The all-users feed leaves out internal team / test accounts.
            $query->whereNotIn('user_id', $this->exclusion->userIds());
        }
        if (! empty($filters['after_id'])) {
            $query->where('id', '>', (int) $filters['after_id']);
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $this->toStorage(Carbon::parse($filters['from'], $this->adminTimezone())->startOfDay()));
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $this->toStorage(Carbon::parse($filters['to'], $this->adminTimezone())->endOfDay()));
        }

        return $query->limit($limit)->get()->map(fn ($a) => $this->presentActivity($a));
    }

    public function presentActivity(IncomeTrackerActivity $activity): array
    {
        $time = $activity->created_at?->copy()->setTimezone($this->adminTimezone());

        return [
            'id'          => $activity->id,
            'user_id'     => $activity->user_id,
            'user_name'   => $activity->user?->name ?: ($activity->user?->user_name ?: 'User #' . $activity->user_id),
            'action'      => $activity->action,
            'description' => $activity->description,
            'backfilled'  => $activity->backfilled,
            'time'        => $time?->format('M d, Y h:i A'),
            'ago'         => $time?->diffForHumans(),
            'detail_url'  => route('admin.income-tracker.show', $activity->user_id),
        ];
    }

    /**
     * Every user who has used the income tracker, with last activity and money totals.
     */
    public function usersOverview(): Collection
    {
        $monthStart = $this->toStorage(Carbon::now($this->adminTimezone())->subDays(29)->startOfDay());

        $usage = $this->counted()->select(
            'user_id',
            DB::raw('MAX(created_at) as last_activity_at'),
            DB::raw('COUNT(*) as total_actions')
        )
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as actions_30d', [$monthStart->format('Y-m-d H:i:s')])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        if ($usage->isEmpty()) {
            return collect();
        }

        $totals = $this->moneyTotals($usage->keys()->all());

        $users = User::whereIn('id', $usage->keys())
            ->get(['id', 'name', 'user_name', 'email', 'status'])
            ->keyBy('id');

        return $usage->map(function ($row) use ($users, $totals) {
            $user = $users->get($row->user_id);
            $last = Carbon::parse($row->last_activity_at, date_default_timezone_get())->setTimezone($this->adminTimezone());

            return array_merge([
                'user_id'          => $row->user_id,
                'name'             => $user?->name ?: ($user?->user_name ?: 'User #' . $row->user_id),
                'email'            => $user?->email,
                'status'           => $user?->status,
                'last_activity'    => $last->format('M d, Y h:i A'),
                'last_activity_ts' => $last->timestamp,
                'last_activity_ago'=> $last->diffForHumans(),
                'total_actions'    => (int) $row->total_actions,
                'actions_30d'      => (int) $row->actions_30d,
                'detail_url'       => route('admin.income-tracker.show', $row->user_id),
            ], $totals[$row->user_id] ?? $this->emptyTotals());
        })
            ->sortByDesc('last_activity_ts')
            ->values();
    }

    /**
     * Money totals per user, using the same definitions as the app's earning summary:
     * earned = paid + received + partial paid so far; pending = pending + owed + partial remaining.
     *
     * @param  int[]  $userIds
     * @return array<int, array>
     */
    public function moneyTotals(array $userIds): array
    {
        $rows = TaskPayment::whereIn('user_id', $userIds)
            ->select(
                'user_id',
                'payment_status',
                DB::raw('COUNT(*) as entries'),
                DB::raw('COALESCE(SUM(payment), 0) as payment_sum'),
                DB::raw('COALESCE(SUM(paid_amount), 0) as paid_sum')
            )
            ->groupBy('user_id', 'payment_status')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $t = $totals[$row->user_id] ?? $this->emptyTotals();
            $payment = (float) $row->payment_sum;
            $paid    = (float) $row->paid_sum;

            $t['entries'] += (int) $row->entries;

            switch ($row->payment_status) {
                case 'paid':
                case 'received':
                    $t['earned'] += $payment;
                    $t[$row->payment_status] += $payment;
                    break;
                case 'pending':
                case 'owed':
                    $t['pending_total'] += $payment;
                    $t[$row->payment_status] += $payment;
                    break;
                case 'partial':
                    $t['earned']           += $paid;
                    $t['pending_total']    += max($payment - $paid, 0);
                    $t['partial_paid']     += $paid;
                    $t['partial_remaining']+= max($payment - $paid, 0);
                    break;
                case 'borrowed':
                case 'return':
                    $t[$row->payment_status] += $payment;
                    break;
            }

            $totals[$row->user_id] = $t;
        }

        return array_map(fn ($t) => array_map(
            fn ($v) => is_float($v) ? round($v, 2) : $v,
            $t
        ), $totals);
    }

    public function emptyTotals(): array
    {
        return [
            'entries'           => 0,
            'earned'            => 0.0,
            'pending_total'     => 0.0,
            'paid'              => 0.0,
            'received'          => 0.0,
            'pending'           => 0.0,
            'owed'              => 0.0,
            'borrowed'          => 0.0,
            'return'            => 0.0,
            'partial_paid'      => 0.0,
            'partial_remaining' => 0.0,
        ];
    }

    /**
     * A user's income entries (all task_payments, including rows created
     * automatically from work history), newest first.
     */
    public function userEntries(User $user, array $filters = []): Collection
    {
        $query = TaskPayment::where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $this->toStorage(Carbon::parse($filters['from'], $this->adminTimezone())->startOfDay()));
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $this->toStorage(Carbon::parse($filters['to'], $this->adminTimezone())->endOfDay()));
        }

        return $query->get()->map(function (TaskPayment $p) {
            // Settled by status alone (no partial payments recorded) means fully paid.
            $settled = in_array($p->payment_status, TaskPayment::SETTLED_STATUSES, true);

            return [
                'id'        => $p->id,
                'title'     => $p->payment_title,
                'amount'    => number_format((float) $p->payment, 2, '.', ''),
                'paid'      => number_format($settled ? (float) $p->payment : (float) $p->paid_amount, 2, '.', ''),
                'remaining' => $settled ? '0.00' : $p->remaining,
                'status'    => $p->payment_status,
                'date'      => $p->create_date,
                'note'      => $p->note,
                'source'    => $p->task_id ? 'Work history' : 'Manual',
                'created'   => $p->created_at?->copy()->setTimezone($this->adminTimezone())->format('M d, Y h:i A'),
            ];
        });
    }

    /** Last time the user did anything in the income tracker, in admin time. */
    public function lastUsed(User $user): ?string
    {
        $last = IncomeTrackerActivity::where('user_id', $user->id)->max('created_at');

        return $last
            ? Carbon::parse($last, date_default_timezone_get())->setTimezone($this->adminTimezone())->format('M d, Y h:i A')
            : null;
    }
}
