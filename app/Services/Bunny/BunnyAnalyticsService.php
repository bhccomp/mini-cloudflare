<?php

namespace App\Services\Bunny;

use App\Models\Site;
use App\Models\SiteAnalyticsMetric;
use Illuminate\Support\Arr;

class BunnyAnalyticsService
{
    public function __construct(protected BunnyApiService $api, protected BunnyLogsService $logs) {}

    public function syncSiteMetrics(Site $site): ?SiteAnalyticsMetric
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return null;
        }

        $stats = $this->fetchStats($zoneId);
        $events = collect($this->logs->recentLogs($site, 400));
        $requestsSeries = $this->chartSeries($stats, ['RequestsServedChart']);
        $error4xxSeries = $this->chartSeries($stats, ['Error4xxChart']);
        $error5xxSeries = $this->chartSeries($stats, ['Error5xxChart']);

        $total24h = (int) ($this->readNumber($stats, ['requests', 'Requests', 'totalRequests', 'TotalRequests', 'TotalRequestsServed']) ?? $events->count());
        $cacheHitRatio = (float) ($this->readNumber($stats, ['cacheHitRate', 'CacheHitRate', 'cacheHitRatio', 'CacheHitRatio']) ?? 0);
        $cached24h = (int) round($total24h * max(0, min($cacheHitRatio, 100)) / 100);
        $origin24h = max(0, $total24h - $cached24h);

        $blocked24h = $events->filter(function (array $event): bool {
            return in_array(strtoupper((string) ($event['action'] ?? 'ALLOW')), ['BLOCK', 'DENY', 'CHALLENGE', 'CAPTCHA'], true)
                || (int) ($event['status_code'] ?? 200) === 403;
        })->count();

        if ($events->isEmpty()) {
            $blockedFromErrors = (int) round(array_sum($error4xxSeries) + array_sum($error5xxSeries));
            $blocked24h = min($total24h, max(0, $blockedFromErrors));
        }

        $allowed24h = max(0, $total24h - $blocked24h);

        $trendLabels = collect(range(6, 0))
            ->map(fn (int $days) => now()->subDays($days)->format('D'))
            ->values()
            ->all();

        $blockedTrend = $events->isNotEmpty()
            ? $this->trendFromEvents($events, true)
            : $this->trendFromStatsSeries($error4xxSeries, $error5xxSeries);

        $allowedTrend = $events->isNotEmpty()
            ? $this->trendFromEvents($events, false)
            : $this->trendFromRequestsSeries($requestsSeries, $blockedTrend);

        $regionalTraffic = $events->groupBy(fn (array $event) => $this->regionForCountry((string) ($event['country'] ?? '')))
            ->map(fn ($group) => $group->count())
            ->toArray();

        $regionalThreat = $events
            ->filter(fn (array $event) => in_array(strtoupper((string) ($event['action'] ?? 'ALLOW')), ['BLOCK', 'DENY', 'CHALLENGE', 'CAPTCHA'], true) || (int) ($event['status_code'] ?? 200) === 403)
            ->groupBy(fn (array $event) => $this->regionForCountry((string) ($event['country'] ?? '')))
            ->map(fn ($group) => $group->count())
            ->toArray();

        $payload = [
            'blocked_requests_24h' => $blocked24h,
            'allowed_requests_24h' => $allowed24h,
            'total_requests_24h' => $total24h,
            'cache_hit_ratio' => round($cacheHitRatio, 2),
            'cached_requests_24h' => $cached24h,
            'origin_requests_24h' => $origin24h,
            'trend_labels' => $trendLabels,
            'blocked_trend' => $blockedTrend,
            'allowed_trend' => $allowedTrend,
            'regional_traffic' => $regionalTraffic,
            'regional_threat' => $regionalThreat,
            'source' => [
                'mode' => 'bunny_live',
                'zone_id' => $zoneId,
            ],
            'captured_at' => now(),
        ];

        return SiteAnalyticsMetric::query()->updateOrCreate(
            ['site_id' => $site->id],
            $payload
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchStats(int $zoneId): array
    {
        $from = now()->subDay()->toDateString();
        $to = now()->toDateString();

        $responses = [
            $this->api->client()->get('/statistics', ['pullZone' => $zoneId, 'dateFrom' => $from, 'dateTo' => $to]),
            $this->api->client()->get("/pullzone/{$zoneId}/statistics", ['dateFrom' => $from, 'dateTo' => $to]),
        ];

        foreach ($responses as $response) {
            if ($response->successful()) {
                $payload = $response->json();

                if (is_array($payload)) {
                    return $payload;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    protected function readNumber(array $stats, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = Arr::get($stats, $key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    protected function trendFromEvents(\Illuminate\Support\Collection $events, bool $blocked): array
    {
        return collect(range(6, 0))
            ->map(function (int $days) use ($events, $blocked): int {
                $day = now()->subDays($days)->format('Y-m-d');

                return $events->filter(function (array $event) use ($day, $blocked): bool {
                    $timestamp = $event['timestamp'] ?? null;

                    if (! $timestamp) {
                        return false;
                    }

                    $isBlocked = in_array(strtoupper((string) ($event['action'] ?? 'ALLOW')), ['BLOCK', 'DENY', 'CHALLENGE', 'CAPTCHA'], true)
                        || (int) ($event['status_code'] ?? 200) === 403;

                    return $timestamp->format('Y-m-d') === $day && ($blocked ? $isBlocked : ! $isBlocked);
                })->count();
            })
            ->values()
            ->all();
    }

    protected function trendFromStatsSeries(array $seriesA, array $seriesB = []): array
    {
        return collect(range(6, 0))
            ->map(function (int $days) use ($seriesA, $seriesB): int {
                $day = now()->subDays($days)->format('Y-m-d');

                return (int) round(($seriesA[$day] ?? 0) + ($seriesB[$day] ?? 0));
            })
            ->values()
            ->all();
    }

    protected function trendFromRequestsSeries(array $requestsSeries, array $blockedTrend): array
    {
        return collect(range(6, 0))
            ->map(function (int $days, int $index) use ($requestsSeries, $blockedTrend): int {
                $day = now()->subDays($days)->format('Y-m-d');
                $total = (int) round($requestsSeries[$day] ?? 0);

                return max(0, $total - (int) ($blockedTrend[$index] ?? 0));
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<int, string>  $keys
     * @return array<string, float>
     */
    protected function chartSeries(array $stats, array $keys): array
    {
        foreach ($keys as $key) {
            $value = Arr::get($stats, $key);

            if (! is_array($value)) {
                continue;
            }

            $series = [];

            foreach ($value as $timestamp => $number) {
                if (! is_numeric($number)) {
                    continue;
                }

                $date = substr((string) $timestamp, 0, 10);
                $series[$date] = (float) $number;
            }

            if ($series !== []) {
                return $series;
            }
        }

        return [];
    }

    protected function regionForCountry(string $country): string
    {
        $country = strtoupper($country);

        return match (true) {
            in_array($country, ['US', 'CA', 'MX'], true) => 'North America',
            in_array($country, ['GB', 'DE', 'FR', 'NL', 'ES', 'IT', 'SE', 'NO', 'PL', 'RO'], true) => 'Europe',
            in_array($country, ['IN', 'JP', 'SG', 'AU', 'NZ', 'KR', 'CN', 'HK'], true) => 'Asia Pacific',
            in_array($country, ['BR', 'AR', 'CL', 'CO', 'PE'], true) => 'South America',
            default => 'Other',
        };
    }
}
