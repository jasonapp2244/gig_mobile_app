<?php

namespace App\Http\Controllers\Admin;

use App\Models\Task;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AdminJobMonitoringController extends Controller
{
    public function index()
    {
        $jobs = Task::with(['taskPayments', 'employer'])
            ->orderBy('created_at', 'desc')
            ->get();
        // dd($jobs->toArray());
        return view('admin.job_monitoring', compact('jobs'));
    }

    // AJAX ke liye
    public function fetchJobs()
    {
        $jobs = Task::with(['taskPayments', 'employer'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'jobs' => $jobs
        ]);
    }

    public function show($id)
    {
        $job = Task::with([
            'user',
            'employer',
            'taskPayments' => function ($q) {
                $q->select('id', 'task_id', 'user_id', 'payment_title', 'payment', 'payment_status', 'created_at');
            }
        ])->findOrFail($id);
        // dd($job->toArray());
        return view('admin.job_details', compact('job'));
    }
}
