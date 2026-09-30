<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncomeTrackerActivity;
use App\Models\User;
use App\Services\IncomeTrackerActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminIncomeTrackerController extends Controller
{
    public function __construct(protected IncomeTrackerActivityService $activity)
    {
    }

    /** Users list page (rows are loaded and refreshed by AJAX). */
    public function index()
    {
        return view('admin.income_tracker.index', [
            'refreshSeconds' => $this->refreshSeconds(),
        ]);
    }

    /** JSON: users who have used the income tracker. */
    public function usersData()
    {
        try {
            return response()->json([
                'success'    => true,
                'users'      => $this->activity->usersOverview(),
                'updated_at' => $this->updatedAt(),
            ]);
        } catch (Throwable $e) {
            Log::error('Income tracker users data failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to load income tracker users.'], 500);
        }
    }

    /** JSON: dashboard widget — active users, trend and recent activity feed. */
    public function dashboardData(Request $request)
    {
        $request->validate(['after_id' => 'nullable|integer|min:0']);

        try {
            return response()->json([
                'success'    => true,
                'stats'      => $this->activity->dashboardStats(),
                'activities' => $this->activity->activities(['after_id' => $request->after_id], 15),
                'updated_at' => $this->updatedAt(),
            ]);
        } catch (Throwable $e) {
            Log::error('Income tracker dashboard data failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to load income tracker data.'], 500);
        }
    }

    /** User detail page. */
    public function show(User $user)
    {
        abort_unless($user->role === 'user', 404);

        return view('admin.income_tracker.show', [
            'user'           => $user,
            'refreshSeconds' => $this->refreshSeconds(),
            'statuses'       => ['pending', 'paid', 'owed', 'received', 'borrowed', 'return', 'partial'],
            'actions'        => [
                IncomeTrackerActivity::ACTION_VIEWED          => 'Opened income tracker',
                IncomeTrackerActivity::ACTION_CREATED         => 'Added entry',
                IncomeTrackerActivity::ACTION_STATUS_CHANGED  => 'Status changed',
                IncomeTrackerActivity::ACTION_PARTIAL_PAYMENT => 'Partial payment',
                IncomeTrackerActivity::ACTION_UPDATED         => 'Updated entry',
                IncomeTrackerActivity::ACTION_DELETED         => 'Deleted entry',
            ],
        ]);
    }

    /**
     * JSON: user detail — summary cards, entries and activity timeline.
     * With after_id only newer timeline rows are returned.
     */
    public function userData(Request $request, User $user)
    {
        abort_unless($user->role === 'user', 404);

        $filters = $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'status'   => 'nullable|in:pending,paid,owed,received,borrowed,return,partial',
            'action'   => 'nullable|in:viewed,created,status_changed,partial_payment,updated,deleted',
            'after_id' => 'nullable|integer|min:0',
        ]);

        try {
            $timelineFilters = [
                'user_id'  => $user->id,
                'after_id' => $filters['after_id'] ?? null,
                'action'   => $filters['action'] ?? null,
                'from'     => $filters['from'] ?? null,
                'to'       => $filters['to'] ?? null,
            ];

            return response()->json([
                'success'    => true,
                'summary'    => $this->activity->moneyTotals([$user->id])[$user->id] ?? $this->activity->emptyTotals(),
                'last_used'  => $this->activity->lastUsed($user),
                'entries'    => $this->activity->userEntries($user, $filters),
                'timeline'   => $this->activity->activities($timelineFilters, 200),
                'updated_at' => $this->updatedAt(),
            ]);
        } catch (Throwable $e) {
            Log::error('Income tracker user data failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to load user income tracker data.'], 500);
        }
    }

    protected function refreshSeconds(): int
    {
        return max(30, (int) config('income_tracker.refresh_seconds', 300));
    }

    protected function updatedAt(): string
    {
        return now()->setTimezone($this->activity->adminTimezone())->format('h:i A');
    }
}
