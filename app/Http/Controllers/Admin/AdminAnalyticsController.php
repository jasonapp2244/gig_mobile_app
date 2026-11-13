<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseAnalyticsService;
use Illuminate\Http\Request;
use Throwable;

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

        // Last safety net: whatever fails on the server, the page gets JSON it can explain
        try {
            $report = $this->analytics->report((int) ($validated['range'] ?? 28));

            // Realtime uses separate Google servers and quota, so it can still show live users
            // while standard reports are down (stale data or a "not responding" message)
            $temporary = [FirebaseAnalyticsService::STATUS_RATE_LIMITED, FirebaseAnalyticsService::STATUS_UNAVAILABLE];
            if ($report['status'] === FirebaseAnalyticsService::STATUS_OK || in_array($report['status'], $temporary, true)) {
                $report['realtime'] = $this->analytics->realtimeActiveUsers();
            }
        } catch (Throwable $e) {
            try {
                report($e);
            } catch (Throwable) {
                // the log itself may be what is broken
            }
            $report = [
                'status'  => FirebaseAnalyticsService::STATUS_ERROR,
                'message' => 'The server could not load Firebase Analytics (' . class_basename($e) . '). Check that storage/ is writable by the site user.',
            ];
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
