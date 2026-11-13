<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
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
 * Google allows only ~10 report requests in flight per property and is sometimes
 * slow on recent data. So: one build at a time (cache lock); each page section is a
 * separate request and cache entry, sent in parallel; a slow section uses its last
 * good copy and is retried on the next load; analytics:warm keeps the cache filled.
 */
class FirebaseAnalyticsService
{
    public const RANGES = [7, 28, 90];

    public const STATUS_OK = 'ok';
    public const STATUS_NOT_CONFIGURED = 'not_configured';
    public const STATUS_API_DISABLED = 'api_disabled';
    public const STATUS_NO_ACCESS = 'no_access';
    public const STATUS_RATE_LIMITED = 'rate_limited';
    /** Google's reporting servers fail or don't answer (HTTP 5xx / timeout): a Google-side problem. */
    public const STATUS_UNAVAILABLE = 'google_unavailable';
    public const STATUS_ERROR = 'error';

    /** Statuses that pause requests to Google and fall back to the last good report. */
    protected const TEMPORARY_STATUSES = [self::STATUS_RATE_LIMITED, self::STATUS_UNAVAILABLE];

    /** Waits (ms) before each retry when Google answers "quota exhausted" (HTTP 429). */
    protected const RETRY_DELAYS_MS = [1500, 3000];

    /** After a "quota exhausted" (429) answer, no requests go to Google for this long (Refresh doesn't skip it). */
    protected const COOLDOWN_MINUTES = 5;

    /** Per-section wait. Google often finishes a slow section anyway and answers the retry from its cache. */
    protected const PIECE_TIMEOUT_SECONDS = 25;
    protected const COOLDOWN_CACHE_KEY = 'ga4.cooldown';

    protected const UNAVAILABLE_MESSAGE = 'Google Analytics is not responding right now. This is a problem on Google\'s side, not in the admin panel.';

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

        return $this->load($days, false);
    }

    /**
     * Rebuild the report for analytics:warm: fetches sections that are missing or expired
     * (fresh ones are reused), so the page always opens with cached numbers.
     */
    public function warm(int $days): array
    {
        return $this->load(in_array($days, self::RANGES, true) ? $days : 28, true);
    }

    protected function load(int $days, bool $force): array
    {
        // Google answered "quota exhausted" a moment ago: send nothing until the pause ends
        $cooldown = Cache::get(self::COOLDOWN_CACHE_KEY);
        if (is_array($cooldown)) {
            return $this->lastGoodOr(new AnalyticsException(
                $cooldown['status'],
                $cooldown['message'] . ' The page will try again after ' . Carbon::parse($cooldown['until'])->format('H:i') . ' (server time).'
            ), $days);
        }

        try {
            // One build at a time (any range): parallel page loads wait for it instead of
            // each sending their own requests to Google (which exhausts the concurrency quota).
            return Cache::lock('ga4.build', 60)->block(50, function () use ($days, $force) {
                $cached = Cache::get($this->reportCacheKey($days));
                if (! $force && $cached !== null) {
                    return $cached;
                }

                // Sections that are still fresh are reused; only missing/expired ones go to Google
                $report = $this->buildReport($this->propertyId(), $days);

                if (empty($report['partial']) && empty($report['stale'])) {
                    $this->cachePut($this->reportCacheKey($days), $report, now()->addMinutes($this->cacheMinutes()));
                    $this->cachePut($this->lastGoodCacheKey($days), $report, now()->addDays(7));
                } else {
                    // Some sections are still loading: retry them on a load a minute from now
                    $this->cachePut($this->reportCacheKey($days), $report, now()->addMinute());
                }

                return $report;
            });
        } catch (LockTimeoutException) {
            return $this->lastGoodOr(new AnalyticsException(self::STATUS_UNAVAILABLE, 'Google Analytics is still loading this report. Please try again in a minute.'), $days);
        } catch (AnalyticsException $e) {
            if (! in_array($e->status, self::TEMPORARY_STATUSES, true)) {
                return $this->failure($e);
            }

            return $this->lastGoodOr($e, $days);
        } catch (ConnectionException $e) {
            $this->log('warning', 'Firebase Analytics timeout: ' . $e->getMessage());

            return $this->lastGoodOr(new AnalyticsException(self::STATUS_UNAVAILABLE, self::UNAVAILABLE_MESSAGE), $days);
        } catch (Throwable $e) {
            $this->log('error', 'Firebase Analytics report failed: ' . $e->getMessage());

            return $this->failure(new AnalyticsException(self::STATUS_ERROR, 'Could not load Firebase Analytics: ' . $e->getMessage()));
        }
    }

    /**
     * Users active in the app in the last 30 minutes, or null when unavailable.
     * Realtime has its own quota and servers, so it is still asked while standard
     * reports are down; a failure is remembered for 2 minutes so pages don't wait on it.
     */
    public function realtimeActiveUsers(): ?int
    {
        if (Cache::has('ga4.realtime_down')) {
            return null;
        }

        try {
            return Cache::remember('ga4.realtime', now()->addMinute(), function () {
                $json = $this->handle($this->client(8)->post(
                    self::DATA_API . '/properties/' . $this->propertyId() . ':runRealtimeReport',
                    ['metrics' => [['name' => 'activeUsers']]]
                ));

                return (int) ($json['rows'][0]['metricValues'][0]['value'] ?? 0);
            });
        } catch (Throwable) {
            $this->cachePut('ga4.realtime_down', true, now()->addMinutes(2));

            return null;
        }
    }

    /** Drop cached reports so the next request reads fresh numbers from Google (fallback copies stay). */
    public function flush(): void
    {
        foreach (self::RANGES as $days) {
            Cache::forget($this->reportCacheKey($days));
            foreach (array_keys($this->pieceRequests($days)) as $name) {
                Cache::forget($this->pieceCacheKey($days, $name));
            }
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

    /** One GA4 runReport body per page section. */
    protected function pieceRequests(int $days): array
    {
        $current = ['startDate' => ($days - 1) . 'daysAgo', 'endDate' => 'today', 'name' => 'current'];
        $previous = ['startDate' => (2 * $days - 1) . 'daysAgo', 'endDate' => $days . 'daysAgo', 'name' => 'previous'];

        return [
            'kpis' => [
                'dateRanges' => [$current, $previous],
                'metrics'    => $this->metrics(self::KPI_METRICS),
            ],
            'daily' => [
                'dateRanges' => [$current],
                'dimensions' => [['name' => 'date']],
                'metrics'    => $this->metrics(['activeUsers', 'newUsers']),
                'orderBys'   => [['dimension' => ['dimensionName' => 'date']]],
            ],
            'screens'   => $this->topRequest($current, 'unifiedScreenName', ['screenPageViews', 'activeUsers']),
            'events'    => $this->topRequest($current, 'eventName', ['eventCount', 'totalUsers']),
            'platforms' => $this->topRequest($current, 'platform', ['activeUsers'], 5),
            'countries' => $this->topRequest($current, 'country', ['activeUsers']),
            'versions'  => $this->topRequest($current, 'appVersion', ['activeUsers']),
        ];
    }

    /**
     * Each section is its own request and its own cache entry, so one slow answer from
     * Google only delays that section. Sections still missing come from their last good
     * copy (marked stale) or are listed in "partial" and retried on the next load.
     */
    protected function buildReport(string $property, int $days): array
    {
        $pieces = [];
        $missing = [];
        foreach ($this->pieceRequests($days) as $name => $body) {
            $fresh = $this->cacheGet($this->pieceCacheKey($days, $name));
            if (is_array($fresh)) {
                $pieces[$name] = $fresh;
            } else {
                $missing[$name] = $body;
            }
        }

        $stale = [];
        $partial = [];
        $failures = [];
        $rateLimited = null;
        foreach ($this->fetchPieces($property, $missing) as $name => $result) {
            if (is_array($result)) {
                $pieces[$name] = $result;
                $this->cachePut($this->pieceCacheKey($days, $name), $result, now()->addMinutes($this->cacheMinutes()));
                $this->cachePut($this->pieceCacheKey($days, $name) . '.last_good', $result, now()->addDays(7));
                continue;
            }

            $failures[$name] = $result;
            if ($result->status === self::STATUS_RATE_LIMITED) {
                $rateLimited = $result;
            }
            $lastGood = $this->cacheGet($this->pieceCacheKey($days, $name) . '.last_good');
            if (is_array($lastGood)) {
                $pieces[$name] = $lastGood;
                $stale[] = $name;
            } else {
                $partial[] = $name;
            }
        }

        if ($rateLimited !== null) {
            $this->startCooldown($rateLimited);
        }

        if (! isset($pieces['kpis'])) {
            throw $rateLimited ?? $failures['kpis'] ?? new AnalyticsException(self::STATUS_UNAVAILABLE, self::UNAVAILABLE_MESSAGE);
        }

        $report = [
            'status'       => self::STATUS_OK,
            'property_id'  => $property,
            'range'        => $days,
            'kpis'         => $this->kpis($pieces['kpis']),
            'daily'        => $this->daily($pieces['daily'] ?? [], $days),
            'screens'      => $this->rows($pieces['screens'] ?? []),
            'events'       => $this->rows($pieces['events'] ?? []),
            'platforms'    => $this->rows($pieces['platforms'] ?? []),
            'countries'    => $this->rows($pieces['countries'] ?? []),
            'versions'     => $this->rows($pieces['versions'] ?? []),
            'generated_at' => now()->toIso8601String(),
        ];
        if ($partial) {
            $report['partial'] = $partial;
        }
        if ($stale) {
            $report['stale'] = true;
        }

        return $report;
    }

    /**
     * Sends the requests in parallel (at most 7, under Google's 10-in-flight limit).
     *
     * @return array<string, array|AnalyticsException> GA4 report per name, or why it failed
     */
    protected function fetchPieces(string $property, array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $token = $this->accessToken();
        $url = self::DATA_API . "/properties/{$property}:runReport";
        $responses = Http::pool(function (Pool $pool) use ($requests, $token, $url) {
            foreach ($requests as $name => $body) {
                $pool->as($name)->withToken($token)->acceptJson()->timeout(self::PIECE_TIMEOUT_SECONDS)->post($url, $body);
            }
        });

        $out = [];
        foreach (array_keys($requests) as $name) {
            $response = $responses[$name] ?? null;
            if (! $response instanceof Response) {
                $this->log('warning', "Firebase Analytics: no answer from Google for {$name}" . ($response instanceof Throwable ? ': ' . $response->getMessage() : ''));
                $out[$name] = new AnalyticsException(self::STATUS_UNAVAILABLE, self::UNAVAILABLE_MESSAGE);
                continue;
            }

            try {
                $out[$name] = $this->handle($response);
            } catch (AnalyticsException $e) {
                // Setup problems (API off, no access) apply to every section: fail the page
                if (! in_array($e->status, self::TEMPORARY_STATUSES, true)) {
                    throw $e;
                }
                $out[$name] = $e;
            }
        }

        return $out;
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

    protected function get(string $url): array
    {
        return $this->handle($this->client()->get($url));
    }

    /** Retries only "quota exhausted" (429) answers, which clear within seconds. */
    protected function client(int $timeout = 20): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout($timeout)
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
            $this->log('warning', 'Firebase Analytics quota: ' . $message);
            throw new AnalyticsException(self::STATUS_RATE_LIMITED, 'Google Analytics is busy right now (too many requests at once). Please try again in a minute.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new AnalyticsException(self::STATUS_NO_ACCESS, $message);
        }

        if ($response->serverError()) {
            $this->log('warning', 'Firebase Analytics: Google returned HTTP ' . $response->status() . ': ' . $message);
            throw new AnalyticsException(self::STATUS_UNAVAILABLE, self::UNAVAILABLE_MESSAGE . ' (Google returned HTTP ' . $response->status() . '.)');
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
        $this->cachePut(self::TOKEN_CACHE_KEY, $token['access_token'], now()->addSeconds($ttl));

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

    protected function startCooldown(AnalyticsException $e): void
    {
        $until = now()->addMinutes(self::COOLDOWN_MINUTES);
        $this->cachePut(self::COOLDOWN_CACHE_KEY, [
            'status'  => $e->status,
            // the "try again after HH:MM" part is added when shown
            'message' => trim(preg_replace('/\s*Please try again.*$/s', '', $e->getMessage())),
            'until'   => $until->toIso8601String(),
        ], $until);
    }

    protected function pieceCacheKey(int $days, string $name): string
    {
        return "ga4.piece.{$days}.{$name}";
    }

    /** Cache read that can't break the page. */
    protected function cacheGet(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (Throwable) {
            return null;
        }
    }

    protected function lastGoodCacheKey(int $days): string
    {
        return "ga4.report.{$days}.last_good";
    }

    /** The last good report (marked stale) when Google is busy, otherwise the failure. */
    protected function lastGoodOr(AnalyticsException $e, int $days): array
    {
        try {
            $lastGood = Cache::get($this->lastGoodCacheKey($days));
        } catch (Throwable) {
            $lastGood = null;
        }

        return is_array($lastGood) ? $lastGood + ['stale' => true] : $this->failure($e);
    }

    /**
     * Cache write that can't break the page: if storage isn't writable (e.g. files
     * created by a cron running as root) the report is still returned, just not cached.
     */
    protected function cachePut(string $key, mixed $value, \DateTimeInterface $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable $e) {
            $this->log('error', "Firebase Analytics could not write cache key {$key}: " . $e->getMessage());
        }
    }

    /** Log line that can't break the page (an unwritable log file must not turn into a 500). */
    protected function log(string $level, string $message): void
    {
        try {
            Log::log($level, $message);
        } catch (Throwable) {
            // nothing else we can do; the page still answers
        }
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
