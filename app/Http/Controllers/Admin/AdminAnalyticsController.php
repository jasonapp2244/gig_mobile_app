<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseAnalyticsService;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(protected FirebaseAnalyticsService $analytics)
    {
    }

    /** Firebase Analytics page (numbers are loaded by AJAX). */
    public function index()
    {
        return view('admin.analytics.index', [
            'ranges'              => FirebaseAnalyticsService::RANGES,
            'serviceAccountEmail' => $this->analytics->serviceAccountEmail(),
            'projectId'           => $this->analytics->projectId(),
        ]);
    }

    /** JSON: report for the chosen range plus realtime users. Always 200; problems are in `status`. */
    public function data(Request $request)
    {
        $validated = $request->validate([
            'range' => 'nullable|integer|in:' . implode(',', FirebaseAnalyticsService::RANGES),
        ]);

        $report = $this->analytics->report((int) ($validated['range'] ?? 28));

        if ($report['status'] === FirebaseAnalyticsService::STATUS_OK) {
            $report['realtime'] = $this->analytics->realtimeActiveUsers();
        }

        return response()->json($report);
    }

    /** Clear cached numbers so the next load reads fresh data from Google. */
    public function refresh()
    {
        $this->analytics->flush();

        return response()->json(['success' => true]);
    }
}
