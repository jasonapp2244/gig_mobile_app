<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FirebaseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

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

    protected function fakeGoogle(bool $busyFirst = false): void
    {
        $kpiMetrics = ['activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'userEngagementDuration', 'engagementRate', 'eventCount'];
        $today = now()->format('Ymd');

        Http::fake([
            'analyticsdata.googleapis.com/v1beta/properties/123456:batchRunReports' => ($busyFirst ? Http::sequence()->pushResponse($this->quotaError()) : Http::sequence())
                ->push(['reports' => [
                    $this->report(['dateRange'], $kpiMetrics, [
                        [['current'], [150, 40, 300, 1200, 15000, 0.62, 5000]],
                        [['previous'], [100, 50, 250, 1000, 10000, 0.5, 4000]],
                    ]),
                    $this->report(['date'], ['activeUsers', 'newUsers'], [[[$today], [33, 4]]]),
                    $this->report(['unifiedScreenName'], ['screenPageViews', 'activeUsers'], [[['Dashboard'], [800, 120]]]),
                    $this->report(['eventName'], ['eventCount', 'totalUsers'], [[['screen_view'], [1200, 140]]]),
                    $this->report(['platform'], ['activeUsers'], [[['Android'], [110]], [['iOS'], [40]]]),
                ]])
                ->push(['reports' => [
                    $this->report(['country'], ['activeUsers'], [[['United States'], [90]]]),
                    $this->report(['appVersion'], ['activeUsers'], [[['1.0.5'], [70]]]),
                ]]),
            'analyticsdata.googleapis.com/v1beta/properties/123456:runRealtimeReport' => Http::response(
                $this->report([], ['activeUsers'], [[[], [7]]])
            ),
        ]);
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
            ->assertJsonCount(2, 'platforms');

        $daily = $response->json('daily');
        $this->assertCount(7, $daily);
        $this->assertSame(['date' => now()->toDateString(), 'active' => 33, 'new' => 4], end($daily));
        $this->assertSame(0, $daily[0]['active']);
    }

    public function test_report_is_cached_until_refresh(): void
    {
        $this->fakeToken();
        $this->fakeGoogle();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7')->assertJsonPath('status', 'ok');
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7')->assertJsonPath('status', 'ok');
        Http::assertSentCount(3); // 2 batches + 1 realtime; the second load came from cache

        $this->actingAs($admin, 'admin')->postJson('/admin/analytics/refresh')->assertOk();
        $this->assertFalse(Cache::has('ga4.report.7'));
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

    protected function quotaError(): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => [
            'code' => 429, 'status' => 'RESOURCE_EXHAUSTED',
            'message' => 'Exhausted concurrent requests quota. Please send fewer requests concurrently.',
        ]], 429);
    }

    public function test_busy_google_is_retried_then_succeeds(): void
    {
        Sleep::fake();
        $this->fakeToken();
        $this->fakeGoogle(busyFirst: true); // first batch call answers 429, the retry succeeds

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=7')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('kpis.activeUsers.value', 150);

        Sleep::assertSleptTimes(1);
    }

    public function test_busy_google_serves_the_last_good_report(): void
    {
        Sleep::fake();
        $this->fakeToken();
        Cache::put('ga4.report.7.last_good', ['status' => 'ok', 'generated_at' => '2026-11-13T10:00:00Z', 'kpis' => ['activeUsers' => ['value' => 42]]], now()->addDay());
        Http::fake(['analyticsdata.googleapis.com/*' => $this->quotaError()]);

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=7')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('stale', true)
            ->assertJsonPath('kpis.activeUsers.value', 42);
    }

    public function test_busy_google_without_earlier_data_reports_rate_limited(): void
    {
        Sleep::fake();
        $this->fakeToken();
        Http::fake(['analyticsdata.googleapis.com/*' => $this->quotaError()]);

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=7')
            ->assertOk()
            ->assertJsonPath('status', 'rate_limited');

        Http::assertSentCount(3); // first try + 2 retries, then it gives up
        $this->assertFalse(Cache::has('ga4.report.7'), 'Failures must not be cached');
    }

    public function test_parallel_load_waits_for_the_running_build_instead_of_calling_google(): void
    {
        Sleep::fake(syncWithCarbon: true); // lets the 50 s lock wait pass instantly
        $this->fakeToken();
        Http::fake();
        Cache::put('ga4.report.90.last_good', ['status' => 'ok', 'kpis' => ['activeUsers' => ['value' => 9]]], now()->addDay());

        $running = Cache::lock('ga4.build', 90);
        $this->assertTrue($running->get(), 'simulate another range being built right now');

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=90')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('stale', true);

        Http::assertNothingSent();
        $running->release();
    }

    public function test_google_timeout_is_reported_as_busy_not_as_a_raw_curl_error(): void
    {
        $this->fakeToken();
        Http::fake(['analyticsdata.googleapis.com/*' => Http::failedConnection('cURL error 28: Operation timed out after 20001 milliseconds')]);

        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'rate_limited')
            ->assertJsonMissingPath('kpis');

        Http::assertSentCount(1); // a timeout is not retried: it would only add to the jam
    }

    public function test_after_busy_google_nothing_is_sent_during_the_cooldown(): void
    {
        $this->fakeToken();
        Http::fake(['analyticsdata.googleapis.com/*' => Http::failedConnection()]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=28')->assertJsonPath('status', 'rate_limited');
        $this->actingAs($admin, 'admin')->postJson('/admin/analytics/refresh')->assertOk();
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7')->assertJsonPath('status', 'rate_limited');
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=90')->assertJsonPath('status', 'rate_limited');

        Http::assertSentCount(1); // only the first load reached Google; Refresh doesn't skip the cooldown

        // After the cooldown the next load asks Google again
        $this->travel(6)->minutes();
        $this->actingAs($admin, 'admin')->getJson('/admin/analytics/data?range=7');
        Http::assertSentCount(2);
    }

    public function test_unwritable_log_and_cache_do_not_turn_busy_google_into_a_500(): void
    {
        Sleep::fake();
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
        Http::fake(['analyticsdata.googleapis.com/*' => $this->quotaError()]);

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/analytics/data?range=28')
            ->assertOk()
            ->assertJsonPath('status', 'rate_limited');
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
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/analytics/refresh')->assertOk();
        $this->assertTrue(Cache::has('ga4.report.7.last_good'), 'Refresh must keep the fallback copy');
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/analytics/data?range=5')
            ->assertStatus(422);
    }
}
