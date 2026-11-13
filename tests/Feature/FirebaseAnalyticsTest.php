<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
