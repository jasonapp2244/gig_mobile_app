<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    public function dashboard()
    {
        if (!Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        try {
            $data = $this->activityService->getDashboardData();
            return view('admin.dashboard', $data);
        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Something went wrong.');
        }
    }

    public function getChartData()
    {
        try {
            // Get last 12 months data
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

            return response()->json([
                'success' => true,
                'labels'  => $chartLabels,
                'values'  => $chartValues,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDashboardDataAjax()
    {
        try {
            $data = $this->activityService->getDashboardData();

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $data['users'],
                    'tasks' => $data['tasks'],
                    'employers' => $data['employers'],
                    'task_payments' => $data['task_payments'],
                    'total_support_email' => $data['total_support_email'],
                    'total_read_email' => $data['total_read_email'],
                    'total_pending_email' => $data['total_pending_email'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getRecentActivitiesAjax()
    {
        try {
            $limit = 20;
            $recentActivities = $this->activityService->getDashboardData($limit)['recent_activities'];


            $html = '';
            foreach ($recentActivities as $activity) {
                $statusBadge = '';
                if ($activity->activity_type === 'support_email') {
                    $statusBadge = '<span class="badge bg-warning">Support</span>';
                } else {
                    $statusBadge = $activity->status === 'active'
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                }

                $html .= '<tr>
                <td>' . e($activity->name) . '</td>
                <td>' . e($activity->activity) . '</td>
                <td>' . Carbon::parse($activity->created_at)
                    ->timezone(config('app.timezone'))
                    ->diffForHumans() . '</td>
                <td>' . $statusBadge . '</td>
                <td>' . Carbon::parse($activity->created_at)
                    ->timezone(config('app.timezone'))
                    ->format('M d, Y') . '</td>
            </tr>';
            }

            return response()->json([
                'success' => true,
                'html' => $html
            ]);
        } catch (\Exception $e) {
            Log::error('Recent Activities AJAX Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load recent activities.'
            ], 500);
        }
    }



    public function users()
    {
        return view('admin.users');
    }
}
