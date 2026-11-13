<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads Firebase Analytics data for the admin panel.
 *
 * Firebase Analytics stores its data in the linked Google Analytics 4 property,
 * so every number here comes from the GA4 Data API, authenticated with the
 * service account named by services.ga4.credentials.
 *
 * Public methods never throw: problems come back as a status array
 * (not_configured, api_disabled, no_access, rate_limited, error) the page can explain.
 *
 * Google allows only ~10 report requests in flight per property. To stay under it:
 * one report build at a time (cache lock), short retries when Google says it is
 * busy, and the last good report is served if Google is still busy after that.
 */
class FirebaseAnalyticsService
{
    public const RANGES = [7, 28, 90];

    public const STATUS_OK = 'ok';
    public const STATUS_NOT_CONFIGURED = 'not_configured';
    public const STATUS_API_DISABLED = 'api_disabled';
    public const STATUS_NO_ACCESS = 'no_access';
    public const STATUS_RATE_LIMITED = 'rate_limited';
    public const STATUS_ERROR = 'error';

    /** Waits (ms) before each retry when Google answers "quota exhausted" (HTTP 429). */
    protected const RETRY_DELAYS_MS = [1500, 3000];

    /** After a busy/timeout answer, no requests go to Google for this long (Refresh doesn't skip it). */
    protected const COOLDOWN_MINUTES = 5;
    protected const COOLDOWN_CACHE_KEY = 'ga4.cooldown_until';

    protected const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';
    protected const DATA_API = 'https://analyticsdata.googleapis.com/v1beta';
    protected const ADMIN_API = 'https://analyticsadmin.googleapis.com/v1beta';
    protected const TOKEN_CACHE_KEY = 'ga4.token';
    protected const PROPERTY_CACHE_KEY = 'ga4.property';

    protected const KPI_METRICS = [
        'activeUsers', 'newUsers', 'sessions', 'screenPageViews',
        'userEngagementDuration', 'engagementRate', 'eventCount',
    ];

    /** Full report for the last $days days, compared with the $days before that. */
    public function report(int $days): array
    {
        if (! in_array($days, self::RANGES, true)) {
            $days = 28;
        }

        $cached = Cache::get($this->reportCacheKey($days));
        if ($cached !== null) {
            return $cached;
        }

        // Google was busy or timing out a moment ago: send nothing until the cooldown ends,
        // so requests still stuck at Google can drain (each new one would add to the jam).
        if (($until = Cache::get(self::COOLDOWN_CACHE_KEY)) !== null) {
            return $this->lastGoodOr(new AnalyticsException(
                self::STATUS_RATE_LIMITED,
                'Google Analytics is busy. The page will try again after ' . Carbon::parse($until)->format('H:i') . ' (server time).'
            ), $days);
        }

        try {
            // One build at a time (any range): parallel page loads wait for it instead of
            // each sending their own batch to Google (which exhausts the concurrency quota).
            // TTL covers the worst case: 2 batches x 20 s timeout + retry waits.
            return Cache::lock('ga4.build', 90)->block(50, function () use ($days) {
                $cached = Cache::get($this->reportCacheKey($days));
                if ($cached !== null) {
                    return $cached;
                }

                $report = $this->buildReport($this->propertyId(), $days);
                Cache::put($this->reportCacheKey($days), $report, now()->addMinutes($this->cacheMinutes()));
                Cache::put($this->lastGoodCacheKey($days), $report, now()->addDays(7));

                return $report;
            });
        } catch (LockTimeoutException) {
            return $this->lastGoodOr(new AnalyticsException(self::STATUS_RATE_LIMITED, 'Google Analytics is still busy loading this report. Please try again in a minute.'), $days);
        } catch (AnalyticsException $e) {
            if ($e->status !== self::STATUS_RATE_LIMITED) {
                return $this->failure($e);
            }
            $this->startCooldown();

            return $this->lastGoodOr($e, $days);
        } catch (ConnectionException $e) {
            // No answer within the timeout: Google is overloaded for this property
            Log::warning('Firebase Analytics timeout: ' . $e->getMessage());
            $this->startCooldown();

            return $this->lastGoodOr(new AnalyticsException(
                self::STATUS_RATE_LIMITED,
                'Google Analytics did not answer in time (it is busy). Please try again in a few minutes.'
            ), $days);
        } catch (Throwable $e) {
            Log::error('Firebase Analytics report failed: ' . $e->getMessage());

            return $this->failure(new AnalyticsException(self::STATUS_ERROR, 'Could not load Firebase Analytics: ' . $e->getMessage()));
        }
    }

    /** Users active in the app in the last 30 minutes, or null when unavailable. */
    public function realtimeActiveUsers(): ?int
    {
        if (Cache::has(self::COOLDOWN_CACHE_KEY)) {
            return null;
        }

        try {
            return Cache::remember('ga4.realtime', now()->addMinute(), function () {
                $json = $this->post(self::DATA_API . '/properties/' . $this->propertyId() . ':runRealtimeReport', [
                    'metrics' => [['name' => 'activeUsers']],
                ]);

                return (int) ($json['rows'][0]['metricValues'][0]['value'] ?? 0);
            });
        } catch (Throwable) {
            return null;
        }
    }

    /** Drop cached reports so the next request reads fresh numbers from Google. */
    public function flush(): void
    {
        foreach (self::RANGES as $days) {
            Cache::forget($this->reportCacheKey($days));
        }
        Cache::forget('ga4.realtime');
        Cache::forget(self::PROPERTY_CACHE_KEY);
    }

    /** Service-account email, shown on the page so the admin knows whom to grant access. */
    public function serviceAccountEmail(): ?string
    {
        $path = $this->credentialsPath();
        if ($path === null) {
            return null;
        }

        $json = json_decode((string) @file_get_contents($path), true);

        return is_array($json) ? ($json['client_email'] ?? null) : null;
    }

    public function projectId(): ?string
    {
        $path = $this->credentialsPath();
        if ($path === null) {
            return null;
        }

        $json = json_decode((string) @file_get_contents($path), true);

        return is_array($json) ? ($json['project_id'] ?? null) : null;
    }

    protected function buildReport(string $property, int $days): array
    {
        $current = ['startDate' => ($days - 1) . 'daysAgo', 'endDate' => 'today', 'name' => 'current'];
        $previous = ['startDate' => (2 * $days - 1) . 'daysAgo', 'endDate' => $days . 'daysAgo', 'name' => 'previous'];

        $first = $this->batch($property, [
            [
                'dateRanges' => [$current, $previous],
                'metrics'    => $this->metrics(self::KPI_METRICS),
            ],
            [
                'dateRanges' => [$current],
                'dimensions' => [['name' => 'date']],
                'metrics'    => $this->metrics(['activeUsers', 'newUsers']),
                'orderBys'   => [['dimension' => ['dimensionName' => 'date']]],
            ],
            $this->topRequest($current, 'unifiedScreenName', ['screenPageViews', 'activeUsers']),
            $this->topRequest($current, 'eventName', ['eventCount', 'totalUsers']),
            $this->topRequest($current, 'platform', ['activeUsers'], 5),
        ]);

        $second = $this->batch($property, [
            $this->topRequest($current, 'country', ['activeUsers']),
            $this->topRequest($current, 'appVersion', ['activeUsers']),
        ]);

        return [
            'status'       => self::STATUS_OK,
            'property_id'  => $property,
            'range'        => $days,
            'kpis'         => $this->kpis($first[0] ?? []),
            'daily'        => $this->daily($first[1] ?? [], $days),
            'screens'      => $this->rows($first[2] ?? []),
            'events'       => $this->rows($first[3] ?? []),
            'platforms'    => $this->rows($first[4] ?? []),
            'countries'    => $this->rows($second[0] ?? []),
            'versions'     => $this->rows($second[1] ?? []),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function topRequest(array $range, string $dimension, array $metrics, int $limit = 10): array
    {
        return [
            'dateRanges' => [$range],
            'dimensions' => [['name' => $dimension]],
            'metrics'    => $this->metrics($metrics),
            'orderBys'   => [['metric' => ['metricName' => $metrics[0]], 'desc' => true]],
            'limit'      => $limit,
        ];
    }

    protected function metrics(array $names): array
    {
        return array_map(fn ($name) => ['name' => $name], $names);
    }

    /** @return array<int, array> one GA4 report per request, in request order */
    protected function batch(string $property, array $requests): array
    {
        $json = $this->post(self::DATA_API . "/properties/{$property}:batchRunReports", ['requests' => $requests]);

        return $json['reports'] ?? [];
    }

    /** KPI values for the current and previous period with the % change between them. */
    protected function kpis(array $report): array
    {
        $values = ['current' => [], 'previous' => []];
        foreach ($this->rows($report) as $row) {
            $period = $row['dateRange'] ?? 'current';
            $values[$period] = $row;
        }

        $out = [];
        foreach (['current', 'previous'] as $period) {
            $row = $values[$period];
            $active = (float) ($row['activeUsers'] ?? 0);
            $row['averageEngagementTime'] = $active > 0 ? ((float) ($row['userEngagementDuration'] ?? 0)) / $active : 0.0;
            $values[$period] = $row;
        }

        foreach (['activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'averageEngagementTime', 'engagementRate', 'eventCount'] as $key) {
            $now = (float) ($values['current'][$key] ?? 0);
            $before = (float) ($values['previous'][$key] ?? 0);
            $out[$key] = [
                'value'    => $now,
                'previous' => $before,
                'change'   => $before > 0 ? round(($now - $before) / $before * 100, 1) : null,
            ];
        }

        return $out;
    }

    /** Daily active/new users with missing days filled in as zero so the chart has no gaps. */
    protected function daily(array $report, int $days): array
    {
        $byDate = [];
        foreach ($this->rows($report) as $row) {
            $byDate[$row['date']] = $row;
        }

        $out = [];
        $start = Carbon::today()->subDays($days - 1);
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $row = $byDate[$day->format('Ymd')] ?? [];
            $out[] = [
                'date'   => $day->toDateString(),
                'active' => (int) ($row['activeUsers'] ?? 0),
                'new'    => (int) ($row['newUsers'] ?? 0),
            ];
        }

        return $out;
    }

    /** Turn a GA4 report into a list of [dimension|metric name => value] rows. */
    protected function rows(array $report): array
    {
        $dimensions = array_column($report['dimensionHeaders'] ?? [], 'name');
        $metrics = array_column($report['metricHeaders'] ?? [], 'name');

        $rows = [];
        foreach ($report['rows'] ?? [] as $row) {
            $item = [];
            foreach ($dimensions as $i => $name) {
                $item[$name] = $row['dimensionValues'][$i]['value'] ?? '';
            }
            foreach ($metrics as $i => $name) {
                $item[$name] = (float) ($row['metricValues'][$i]['value'] ?? 0);
            }
            $rows[] = $item;
        }

        return $rows;
    }

    /** GA4 property id from config, otherwise the first property the service account can see. */
    protected function propertyId(): string
    {
        $configured = trim((string) config('services.ga4.property_id'));
        if ($configured !== '') {
            return preg_replace('/^properties\//', '', $configured);
        }

        return Cache::remember(self::PROPERTY_CACHE_KEY, now()->addDay(), function () {
            $json = $this->get(self::ADMIN_API . '/accountSummaries');

            foreach ($json['accountSummaries'] ?? [] as $account) {
                foreach ($account['propertySummaries'] ?? [] as $summary) {
                    if (! empty($summary['property'])) {
                        return str_replace('properties/', '', $summary['property']);
                    }
                }
            }

            throw new AnalyticsException(
                self::STATUS_NO_ACCESS,
                'The service account has not been added to any Google Analytics property yet.'
            );
        });
    }

    protected function post(string $url, array $body): array
    {
        return $this->handle($this->client()->post($url, $body));
    }

    protected function get(string $url): array
    {
        return $this->handle($this->client()->get($url));
    }

    /** Retries only "quota exhausted" (429) answers, which clear within seconds. */
    protected function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(20)
            ->retry(self::RETRY_DELAYS_MS, 0, function (Throwable $e) {
                return $e instanceof RequestException && $e->response->status() === 429;
            }, false);
    }

    protected function handle(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error') ?? [];
        $message = $error['message'] ?? ('Google returned HTTP ' . $response->status());
        $reasons = array_column($error['details'] ?? [], 'reason');

        if (in_array('SERVICE_DISABLED', $reasons, true)) {
            $url = null;
            foreach ($error['details'] ?? [] as $detail) {
                $url = $url ?? ($detail['metadata']['activationUrl'] ?? null);
            }
            throw new AnalyticsException(self::STATUS_API_DISABLED, $message, $url);
        }

        if ($response->status() === 429 || ($error['status'] ?? null) === 'RESOURCE_EXHAUSTED') {
            Log::warning('Firebase Analytics quota: ' . $message);
            throw new AnalyticsException(self::STATUS_RATE_LIMITED, 'Google Analytics is busy right now (too many requests at once). Please try again in a minute.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new AnalyticsException(self::STATUS_NO_ACCESS, $message);
        }

        throw new AnalyticsException(self::STATUS_ERROR, $message);
    }

    protected function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $path = $this->credentialsPath();
        if ($path === null) {
            throw new AnalyticsException(self::STATUS_NOT_CONFIGURED, 'The Firebase service-account file is not configured (GA4_CREDENTIALS).');
        }

        try {
            $token = (new ServiceAccountCredentials(self::SCOPE, $path))->fetchAuthToken();
        } catch (Throwable) {
            throw new AnalyticsException(self::STATUS_NOT_CONFIGURED, 'The service-account file could not be used to sign in to Google.');
        }

        if (empty($token['access_token'])) {
            throw new AnalyticsException(self::STATUS_NOT_CONFIGURED, 'Google did not return an access token for the service account.');
        }

        $ttl = max(60, (int) ($token['expires_in'] ?? 3600) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token['access_token'], now()->addSeconds($ttl));

        return $token['access_token'];
    }

    protected function credentialsPath(): ?string
    {
        $configured = trim((string) config('services.ga4.credentials'));
        if ($configured === '') {
            return null;
        }

        $path = preg_match('/^([A-Za-z]:[\\\\\/]|\/)/', $configured) ? $configured : base_path($configured);

        return is_file($path) ? $path : null;
    }

    protected function cacheMinutes(): int
    {
        return max(1, (int) config('services.ga4.cache_minutes', 15));
    }

    protected function reportCacheKey(int $days): string
    {
        return "ga4.report.{$days}";
    }

    protected function startCooldown(): void
    {
        $until = now()->addMinutes(self::COOLDOWN_MINUTES);
        Cache::put(self::COOLDOWN_CACHE_KEY, $until->toIso8601String(), $until);
    }

    protected function lastGoodCacheKey(int $days): string
    {
        return "ga4.report.{$days}.last_good";
    }

    /** The last good report (marked stale) when Google is busy, otherwise the failure. */
    protected function lastGoodOr(AnalyticsException $e, int $days): array
    {
        $lastGood = Cache::get($this->lastGoodCacheKey($days));

        return is_array($lastGood) ? $lastGood + ['stale' => true] : $this->failure($e);
    }

    protected function failure(AnalyticsException $e): array
    {
        return [
            'status'         => $e->status,
            'message'        => $e->getMessage(),
            'activation_url' => $e->activationUrl,
        ];
    }
}
