<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\Employer;
use App\Models\ListStory;
use App\Models\SupportEmail;
use App\Models\TaskPayment;
use Illuminate\Support\Facades\DB;


class ActivityService
{
    public function getDashboardData($limit = 10)
    {
        // Counts
        $users = User::where('role', 'user')->where('status', 'active')->count();
        $tasks = Task::where('is_locked', 0)->count();
        $employers = Employer::where('status', 1)->count();
        $task_payments = TaskPayment::where('payment_status', 'paid')->sum('payment');
        $total_support_email = SupportEmail::where('status', 'sent')->count();
        $total_pending_email = SupportEmail::where('is_read', 1)->count();
        $total_read_email = SupportEmail::where('is_read', 0)->count();

        // Recent active users
        $user_list = User::where('role', 'user')
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($u) {
                $u->is_online = $u->isOnline();
                return $u;
            });

        // Recent activities
        $userActivities = User::select(
            'id',
            'name',
            'status',
            DB::raw("'User Signup' as activity"),
            DB::raw("null as amount"),
            'created_at',
            DB::raw("'user' as activity_type")
        )
            ->where('role', 'user')
            ->where('status', 'active');

        $taskActivities = Task::select(
            'user_id as id',
            DB::raw("(SELECT name FROM users WHERE users.id = tasks.user_id) as name"),
            DB::raw("(SELECT status FROM users WHERE users.id = tasks.user_id) as status"),
            DB::raw("CONCAT('Created new task: ', COALESCE(employer, position, 'Unnamed Task')) as activity"),
            DB::raw("null as amount"),
            'created_at',
            DB::raw("'task' as activity_type")
        );

        $paymentActivities = TaskPayment::select(
            'user_id as id',
            DB::raw("(SELECT name FROM users WHERE users.id = task_payments.user_id) as name"),
            DB::raw("(SELECT status FROM users WHERE users.id = task_payments.user_id) as status"),
            DB::raw("CONCAT('Payment completed: $', payment) as activity"),
            'payment as amount',
            'created_at',
            DB::raw("'payment' as activity_type")
        )->where('payment_status', 'paid');

        $listActivities = ListStory::select(
            'user_id as id',
            DB::raw("(SELECT name FROM users WHERE users.id = list_stories.user_id) as name"),
            DB::raw("(SELECT status FROM users WHERE users.id = list_stories.user_id) as status"),
            DB::raw("CONCAT('Created new list: ', title) as activity"),
            DB::raw("null as amount"),
            'created_at',
            DB::raw("'list' as activity_type")
        );

        // Add this inside getDashboardData() before $recent_activities
        $supportEmailActivities = SupportEmail::select(
            'id',
            'name',
            DB::raw("null as status"),
            DB::raw("subject as activity"),
            DB::raw("null as amount"),
            'created_at',
            DB::raw("'support_email' as activity_type")
        )->where('status', 'sent');


        $recent_activities = $userActivities
            ->unionAll($taskActivities)
            ->unionAll($paymentActivities)
            ->unionAll($listActivities)
            ->unionAll($supportEmailActivities) // << added support emails
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // dd($recent_activities->toArray());

        // Chart Data - Last 12 months
        $chartLabels = [];
        $chartValues = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthName = $date->format('M');
            $year = $date->format('Y');
            $month = $date->format('m');

            $chartLabels[] = $monthName;

            $count = DB::table('users')
                ->where('role', 'user')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();

            $chartValues[] = $count;
        }

        return compact(
            'users',
            'tasks',
            'employers',
            'task_payments',
            'user_list',
            'total_support_email',
            'total_read_email',
            'total_pending_email',
            'recent_activities',
            'chartLabels',
            'chartValues'
        );
    }
}
