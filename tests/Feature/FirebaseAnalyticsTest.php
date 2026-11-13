<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FirebaseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /** runReport attempts seen by the fake, including ones that end in a timeout */
    protected int $runReports = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ga4.credentials' => null,
            'services.ga4.property_id' => '123456',
            'services.ga4.cache_minutes' => 15,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /** Skip the real Google sign-in: the service reads the token from cache first. */
    protected function fakeToken(): void
    {
        Cache::put('ga4.token', 'test-token', now()->addHour());
    }

    protected function report(array $dimensions, array $metrics, array $rows): array
    {
        return [
            'dimensionHeaders' => array_map(fn ($n) => ['name' => $n], $dimensions),
            'metricHeaders'    => array_map(fn ($n) => ['name' => $n, 'type' => 'TYPE_INTEGER'], $metrics),
            'rows'             => array_map(fn ($row) => [
                'dimensionValues' => array_map(fn ($v) => ['value' => (string) $v], $row[0]),
                'metricValues'    => array_map(fn ($v) => ['value' => (string) $v], $row[1]),
            ], $rows),
        ];
    }

    /** Google's answer for each page section (one runReport each). */
    protected function sectionReports(): array
    {
        $kpiMetrics = ['activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'userEngagementDuration', 'engagementRate', 'eventCount'];

        return [
            'kpis' => $this->report(['dateRange'], $kpiMetrics, [
                [['current'], [150, 40, 300, 1200, 15000, 0.62, 5000]],
                [['previous'], [100, 50, 250, 1000, 10000, 0.5, 4000]],
            ]),
            'daily'     => $this->report(['date'], ['activeUsers', 'newUsers'], [[[now()->format('Ymd')], [33, 4]]]),
            'screens'   => $this->report(['unifiedScreenName'], ['screenPageViews', 'activeUsers'], [[['Dashboard'], [800, 120]]]),
            'events'    => $this->report(['eventName'], ['eventCount', 'totalUsers'], [[['screen_view'], [1200, 140]]]),
            'platforms' => $this->report(['platform'], ['activeUsers'], [[['Android'], [110]], [['iOS'], [40]]]),
            'countries' => $this->report(['country'], ['activeUsers'], [[['United States'], [90]]]),
            'versions'  => $this->report(['appVersion'], ['activeUsers'], [[['1.0.5'], [70]]]),
        ];
    }

    /** Which page section a runReport request is for. */
    protected function sectionOf(Request $request): string
    {
        if (count($request['dateRanges'] ?? []) === 2) {
            return 'kpis';
        }

        return [
            'date' => 'daily', 'unifiedScreenName' => 'screens', 'eventName' => 'events',
            'platform' => 'platforms', 'country' => 'countries', 'appVersion' => 'versions',
        ][$request['dimensions'][0]['name'] ?? ''] ?? 'unknown';
    }

    /**
     * Fake Google. $sections overrides a section's answer: a response, or a
     * callable(Request) returning one (e.g. Http::failedConnection()).
     */
    protected function fakeGoogle(array $sections = [], int $realtime = 7): void
    {
        Http::fake(function (Request $request) use ($sections, $realtime) {
            if (str_contains($request->url(), '123456:runRealtimeReport')) {
                return Http::response($this->report([], ['activeUsers'], [[[], [$realtime]]]));
            }
            if (! str_contains($request->url(), '123456:runReport')) {
                return null;
            }

            $this->runReports++;
            $section = $this->sectionOf($request);
            $answer = $sections[$section] ?? $sections['*'] ?? null;
            if (is_callable($answer)) {
                $answer = $answer($request);
            }

            return $answer ?? Http::response($this->sectionReports()[$section]);
        });
    }

    protected function runReportsSent(): int
    {
        return $this->runReports;
    }

    protected function quotaError(): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => [
            'code' => 429, 'status' => 'RESOURCE_EXHAUSTED',
            'message' => 'Exhausted concurrent requests quota. Please send fewer requests concurrently.',
        ]], 429);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/analytics')->assertRedirect(route('admin.login'));
        $this->get('/admin/analytics/data')->assertRedirect(route('admin.login'));
    }

    public function test_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/analytics')
            ->assertOk()
            ->assertSee('Firebase Analytics')
            ->assertSee('Property access management');
    }

    public function test_missing_credentials_report_not_configured(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=7')
            ->assertOk()
            ->assertJson(['status' => 'not_configured']);
    }

    public function test_report_returns_kpis_with_change_and_tables(): void
    {
        $this->fakeToken();
        $this->fakeGoogle();

        $response = $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=7')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('property_id', '123456')
            ->assertJsonPath('realtime', 7)
            ->assertJsonPath('kpis.activeUsers.value', 150)
            ->assertJsonPath('kpis.activeUsers.change', 50)
            ->assertJsonPath('kpis.newUsers.change', -20)
            ->assertJsonPath('kpis.averageEngagementTime.value', 100)
            ->assertJsonPath('screens.0.unifiedScreenName', 'Dashboard')
            ->assertJsonPath('events.0.eventName', 'screen_view')
            ->assertJsonPath('countries.0.country', 'United States')
            ->assertJsonPath('versions.0.appVersion', '1.0.5')
            ->assertJsonCount(2, 'platforms')
            ->assertJsonMissingPath('partial')
            ->assertJsonMissingPath('stale');

        $daily = $response->json('daily');
        $this->assertCount(7, $daily);
        $this->assertSame(['date' => now()->toDateString(), 'active' => 33, 'new' => 4], end($daily));
        $this->assertSame(0, $daily[0]['active']);
        $this->assertSame(7, $this->runReportsSent(), 'one request per section');
    }

    public function test_report_is_cached_until_refresh(): void
    {
        $this->fakeToken();
        $this->fakeGoogle();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7')->assertJsonPath('status', 'ok');
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7')->assertJsonPath('status', 'ok');
        $this->assertSame(7, $this->runReportsSent(), 'the second load came from cache');

        $this->actingAs($admin, 'admin')->postJson('/admin/analytics/refresh')->assertOk();
        $this->assertFalse(Cache::has('ga4.report.7'));
        $this->assertFalse(Cache::has('ga4.piece.7.kpis'));
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7')->assertJsonPath('status', 'ok');
        $this->assertSame(14, $this->runReportsSent(), 'Refresh reads every section again');
    }

    public function test_disabled_api_is_reported_with_activation_link(): void
    {
        $this->fakeToken();
        Http::fake([
            'analyticsdata.googleapis.com/*' => Http::response(['error' => [
                'code'    => 403,
                'status'  => 'PERMISSION_DENIED',
                'message' => 'Google Analytics Data API has not been used in project 192206418049 before or it is disabled.',
                'details' => [[
                    '@type'    => 'type.googleapis.com/google.rpc.ErrorInfo',
                    'reason'   => 'SERVICE_DISABLED',
                    'metadata' => ['activationUrl' => 'https://console.developers.google.com/apis/api/analyticsdata.googleapis.com/overview?project=192206418049'],
                ]],
            ]], 403),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'api_disabled')
            ->assertJsonPath('activation_url', 'https://console.developers.google.com/apis/api/analyticsdata.googleapis.com/overview?project=192206418049');

        $this->assertFalse(Cache::has('ga4.report.28'), 'Failures must not be cached');
    }

    public function test_permission_denied_is_reported_as_no_access(): void
    {
        $this->fakeToken();
        Http::fake([
            'analyticsdata.googleapis.com/*' => Http::response(['error' => [
                'code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'User does not have sufficient permissions for this property.',
            ]], 403),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data')
            ->assertJsonPath('status', 'no_access');
    }

    public function test_property_is_discovered_when_not_configured(): void
    {
        config(['services.ga4.property_id' => '']);
        $this->fakeToken();
        Http::fake([
            'analyticsadmin.googleapis.com/v1beta/accountSummaries' => Http::response(['accountSummaries' => []]),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data')
            ->assertJsonPath('status', 'no_access');
    }

    public function test_slow_section_still_shows_the_rest_and_is_retried_on_the_next_load(): void
    {
        // Live 2026-11-13: Google answered most sections fast but left some hanging
        $this->fakeToken();
        $screensSlow = true;
        $this->fakeGoogle(['screens' => function (Request $request) use (&$screensSlow) {
            return $screensSlow ? (Http::failedConnection('cURL error 28: Operation timed out'))($request) : null;
        }]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('kpis.activeUsers.value', 150)
            ->assertJsonPath('countries.0.country', 'United States')
            ->assertJsonPath('partial', ['screens'])
            ->assertJsonPath('screens', []);

        // Google has the answer now; a load a minute later asks only for the missing section
        $screensSlow = false;
        $this->travel(2)->minutes();
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('screens.0.unifiedScreenName', 'Dashboard')
            ->assertJsonMissingPath('partial');

        $this->assertSame(8, $this->runReportsSent(), '7 sections, then only the slow one again');
    }

    public function test_slow_section_uses_its_last_good_copy(): void
    {
        $this->fakeToken();
        Cache::put('ga4.piece.28.countries.last_good', $this->report(['country'], ['activeUsers'], [[['Canada'], [5]]]), now()->addDay());
        $this->fakeGoogle(['countries' => fn (Request $request) => (Http::failedConnection())($request)]);

        $this->actingAs($this->admin(), 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('stale', true)
            ->assertJsonPath('countries.0.country', 'Canada')
            ->assertJsonMissingPath('partial');
    }

    public function test_quota_answer_pauses_requests_for_five_minutes(): void
    {
        $this->fakeToken();
        $this->fakeGoogle(['*' => $this->quotaError()]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7')
            ->assertOk()
            ->assertJsonPath('status', 'rate_limited');
        $this->assertSame(7, $this->runReportsSent());
        $this->assertFalse(Cache::has('ga4.report.7'), 'Failures must not be cached');

        $this->actingAs($admin, 'admin')->postJson('/admin/analytics/refresh')->assertOk();
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertJsonPath('status', 'rate_limited')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'will try again after'));
        $this->assertSame(7, $this->runReportsSent(), 'nothing sent during the pause, even after Refresh');

        $this->travel(6)->minutes();
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7');
        $this->assertSame(14, $this->runReportsSent(), 'after the pause Google is asked again');
    }

    public function test_quota_answer_serves_the_last_good_report(): void
    {
        $this->fakeToken();
        Cache::put('ga4.report.7.last_good', ['status' => 'ok', 'generated_at' => '2026-11-13T10:00:00Z', 'kpis' => ['activeUsers' => ['value' => 42]]], now()->addDay());
        $this->fakeGoogle(['*' => $this->quotaError()]);

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=7')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('stale', true)
            ->assertJsonPath('kpis.activeUsers.value', 42);
    }

    public function test_timeouts_do_not_pause_so_the_next_load_tries_again(): void
    {
        $this->fakeToken();
        $this->fakeGoogle(['*' => fn (Request $request) => (Http::failedConnection('cURL error 28: Operation timed out after 25001 milliseconds'))($request)]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'google_unavailable')
            ->assertJsonPath('message', fn ($m) => str_contains($m, "problem on Google's side") && ! str_contains($m, 'cURL'))
            ->assertJsonMissingPath('kpis');
        $this->assertSame(7, $this->runReportsSent(), 'each section once, no retry storm');

        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=28');
        $this->assertSame(14, $this->runReportsSent(), 'no pause: Google often answers the retry from its cache');
    }

    public function test_google_server_error_is_reported_as_google_side_and_realtime_still_shows(): void
    {
        // Live 2026-11-13: standard reports returned HTTP 502, realtime answered normally
        $this->fakeToken();
        $this->fakeGoogle(['*' => Http::response('<html>502 Bad Gateway</html>', 502)], realtime: 4);

        $this->actingAs($this->admin(), 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'google_unavailable')
            ->assertJsonPath('realtime', 4)
            ->assertJsonPath('message', fn ($m) => str_contains($m, "problem on Google's side") && str_contains($m, 'HTTP 502'));
    }

    public function test_parallel_load_waits_for_the_running_build_instead_of_calling_google(): void
    {
        Sleep::fake(syncWithCarbon: true); // lets the 50 s lock wait pass instantly
        $this->fakeToken();
        $this->fakeGoogle();
        Cache::put('ga4.report.90.last_good', ['status' => 'ok', 'kpis' => ['activeUsers' => ['value' => 9]]], now()->addDay());

        $running = Cache::lock('ga4.build', 60);
        $this->assertTrue($running->get(), 'simulate another range being built right now');

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=90')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('stale', true);

        $this->assertSame(0, $this->runReportsSent(), 'no report sent while another build runs');
        $running->release();
    }

    public function test_warm_command_fills_the_cache_so_the_page_does_not_wait_on_google(): void
    {
        $this->fakeToken();
        $this->fakeGoogle();

        $this->artisan('analytics:warm')
            ->expectsOutput('28 days: ok')
            ->expectsOutput('7 days: ok')
            ->expectsOutput('90 days: ok')
            ->assertSuccessful();
        $this->assertSame(21, $this->runReportsSent());

        $this->actingAs($this->admin(), 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('kpis.activeUsers.value', 150);
        $this->assertSame(21, $this->runReportsSent(), 'the page load came from the warmed cache');

        // The next run reuses sections that are still fresh, and re-reads them once they expire
        $this->artisan('analytics:warm')->assertSuccessful();
        $this->assertSame(21, $this->runReportsSent());
        $this->travel(16)->minutes();
        $this->artisan('analytics:warm')->assertSuccessful();
        $this->assertSame(42, $this->runReportsSent());
    }

    public function test_warm_command_is_quiet_when_analytics_is_not_set_up(): void
    {
        $this->artisan('analytics:warm')
            ->expectsOutputToContain('not set up (not_configured)')
            ->assertSuccessful();
    }

    public function test_unwritable_log_and_cache_do_not_turn_busy_google_into_a_500(): void
    {
        $admin = $this->admin();

        // Live state: storage/ files owned by root, so the site user can't write cache or logs
        Cache::extend('unwritable', fn () => Cache::repository(new class extends \Illuminate\Cache\ArrayStore {
            public function put($key, $value, $seconds)
            {
                if ($key === 'ga4.token') { // let the test sign in without Google
                    return parent::put($key, $value, $seconds);
                }
                throw new \ErrorException('file_put_contents(storage/framework/cache/data/..): Failed to open stream: Permission denied');
            }
        }));
        config(['cache.stores.unwritable' => ['driver' => 'unwritable'], 'cache.default' => 'unwritable']);
        Log::swap(new class {
            public function __call($method, $args)
            {
                throw new \UnexpectedValueException('The stream or file "storage/logs/laravel.log" could not be opened in append mode: Permission denied');
            }
        });
        $this->fakeToken();
        $this->fakeGoogle(['*' => $this->quotaError()]);

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'rate_limited');
    }

    public function test_unwritable_cache_still_shows_the_data(): void
    {
        Cache::extend('unwritable', fn () => Cache::repository(new class extends \Illuminate\Cache\ArrayStore {
            public function put($key, $value, $seconds)
            {
                if ($key === 'ga4.token') {
                    return parent::put($key, $value, $seconds);
                }
                throw new \ErrorException('Permission denied');
            }
        }));
        config(['cache.stores.unwritable' => ['driver' => 'unwritable'], 'cache.default' => 'unwritable']);
        $this->fakeToken();
        $this->fakeGoogle();

        $this->actingAs($this->admin(), 'admin')->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('kpis.activeUsers.value', 150);
    }

    public function test_any_server_failure_still_answers_json_the_page_can_show(): void
    {
        $this->mock(\App\Services\FirebaseAnalyticsService::class, function ($mock) {
            $mock->shouldReceive('report')->andThrow(new \RuntimeException('disk full'));
        });

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'error');
    }

    public function test_a_successful_load_is_kept_as_last_good(): void
    {
        $this->fakeToken();
        $this->fakeGoogle();

        $this->actingAs($this->admin(), 'admin')->getJson('/admin/analytics/data?range=7')->assertJsonPath('status', 'ok');

        $this->assertEquals(150, Cache::get('ga4.report.7.last_good')['kpis']['activeUsers']['value']);
        $this->assertTrue(Cache::has('ga4.piece.7.screens.last_good'));
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/analytics/refresh')->assertOk();
        $this->assertTrue(Cache::has('ga4.report.7.last_good'), 'Refresh must keep the fallback copy');
        $this->assertTrue(Cache::has('ga4.piece.7.screens.last_good'), 'Refresh must keep section fallbacks');
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=5')
            ->assertStatus(422);
    }
}
