<?php

namespace App\Services\Bunny;

use App\Models\EdgeRequestLog;
use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class BunnyFirewallInsightsService
{
    public function __construct(
        protected BunnyLogsService $logs,
        protected BunnyAnalyticsService $analytics,
    ) {}

    public function getSiteInsights(Site $site): array
    {
        return Cache::remember(
            $this->cacheKey($site->id),
            now()->addMinutes(2),
            fn (): array => $this->buildInsights($site)
        );
    }

    public function forgetSiteInsightsCache(int $siteId): void
    {
        Cache::forget($this->cacheKey($siteId));
    }

    protected function buildInsights(Site $site): array
    {
        $events = $this->localEvents($site);

        if ($events->isEmpty()) {
            $synced = $this->logs->syncToLocalStore($site, 400);

            if ($synced > 0) {
                $events = $this->localEvents($site);
            }
        }

        if ($events->isEmpty()) {
            $events = collect($this->logs->recentLogs($site, 300));
        }

        if ($events->isEmpty()) {
            $metric = $this->analytics->syncSiteMetrics($site) ?: $site->analyticsMetric;
            $total = (int) ($metric?->total_requests_24h ?? 0);
            $blocked = (int) ($metric?->blocked_requests_24h ?? 0);
            $allowed = max(0, $total - $blocked);
            $topCountries = $this->topCountriesFromGeoStats($site, $total);

            return [
                'summary' => [
                    'total' => $total,
                    'blocked' => $blocked,
                    'allowed' => $allowed,
                    'counted' => 0,
                    'block_ratio' => $total > 0 ? round(($blocked / max(1, $total)) * 100, 2) : 0,
                ],
                'top_countries' => $topCountries,
                'top_ips' => [],
                'events' => [],
                'captured_at' => now(),
                'source' => 'bunny_live',
                'message' => $total > 0
                    ? 'Request totals were loaded from edge analytics. Detailed firewall events are not available yet.'
                    : 'No edge telemetry available yet.',
            ];
        }

        $blocked = $events->filter(function (array $event): bool {
            $action = strtoupper((string) ($event['action'] ?? 'ALLOW'));
            $statusCode = (int) ($event['status_code'] ?? 200);

            return in_array($action, ['BLOCK', 'DENY', 'CHALLENGE', 'CAPTCHA'], true)
                || $statusCode === 403;
        })->count();

        $total = $events->count();
        $allowed = max(0, $total - $blocked);

        $topCountries = $events
            ->groupBy('country')
            ->map(fn ($group, string $country): array => ['country' => $country, 'requests' => $group->count()])
            ->sortByDesc('requests')
            ->take(10)
            ->values()
            ->all();

        $topIps = $events
            ->groupBy('ip')
            ->map(fn ($group, string $ip): array => [
                'ip' => $ip,
                'requests' => $group->count(),
                'blocked' => $group->filter(fn (array $event): bool => in_array(strtoupper((string) ($event['action'] ?? 'ALLOW')), ['BLOCK', 'DENY', 'CHALLENGE', 'CAPTCHA'], true) || (int) ($event['status_code'] ?? 200) === 403)->count(),
            ])
            ->sortByDesc('requests')
            ->take(10)
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => $total,
                'blocked' => $blocked,
                'allowed' => $allowed,
                'counted' => 0,
                'block_ratio' => $total > 0 ? round(($blocked / $total) * 100, 2) : 0,
            ],
            'top_countries' => $topCountries,
            'top_ips' => $topIps,
            'events' => $events->take(30)->all(),
            'captured_at' => now(),
            'source' => 'bunny_live',
            'message' => null,
        ];
    }

    protected function cacheKey(int $siteId): string
    {
        return 'firewall-insights:bunny:site:'.$siteId;
    }

    protected function localEvents(Site $site): \Illuminate\Support\Collection
    {
        return EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->where('event_at', '>=', now()->subDays(7))
            ->latest('event_at')
            ->limit(500)
            ->get()
            ->map(function (EdgeRequestLog $log): array {
                return [
                    'timestamp' => $log->event_at,
                    'action' => strtoupper((string) ($log->action ?? 'ALLOW')),
                    'ip' => (string) ($log->ip ?? '-'),
                    'country' => strtoupper((string) ($log->country ?? '??')),
                    'method' => strtoupper((string) ($log->method ?? 'GET')),
                    'uri' => (string) ($log->path ?? '/'),
                    'rule' => (string) ($log->rule ?? 'edge'),
                    'status_code' => (int) ($log->status_code ?? 200),
                    'user_agent' => (string) ($log->user_agent ?? ''),
                ];
            })
            ->values();
    }

    /**
     * @return array<int, array{country:string,requests:int}>
     */
    protected function topCountriesFromGeoStats(Site $site, int $totalRequests): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return [];
        }

        $response = app(BunnyApiService::class)->client()->get('/statistics', [
            'pullZone' => $zoneId,
            'dateFrom' => now()->subDay()->toDateString(),
            'dateTo' => now()->toDateString(),
        ]);

        if (! $response->successful()) {
            return [];
        }

        $geo = Arr::get($response->json(), 'GeoTrafficDistribution', []);

        if (! is_array($geo) || $geo === []) {
            return [];
        }

        $countryTraffic = collect($geo)
            ->map(function ($amount, $edge): ?array {
                if (! is_numeric($amount)) {
                    return null;
                }

                $country = $this->countryCodeFromEdgeLabel((string) $edge);
                if ($country === '') {
                    return null;
                }

                return [
                    'country' => $country,
                    'requests' => (int) round((float) $amount),
                ];
            })
            ->filter()
            ->groupBy('country')
            ->map(fn ($rows, string $country): array => [
                'country' => $country,
                'traffic' => (float) collect($rows)->sum('requests'),
            ])
            ->sortByDesc('traffic')
            ->values()
            ->all();

        if ($countryTraffic === []) {
            return [];
        }

        $trafficSum = max(1.0, (float) collect($countryTraffic)->sum('traffic'));

        return collect($countryTraffic)
            ->map(function (array $row) use ($totalRequests, $trafficSum): array {
                $share = ((float) ($row['traffic'] ?? 0)) / $trafficSum;
                $estimated = $totalRequests > 0
                    ? max(1, (int) round($share * $totalRequests))
                    : (int) round($row['traffic']);

                return [
                    'country' => (string) $row['country'],
                    'requests' => $estimated,
                ];
            })
            ->sortByDesc('requests')
            ->take(10)
            ->values()
            ->all();
    }

    protected function countryCodeFromEdgeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        if (preg_match('/,\\s*([A-Za-z]{2})$/', $label, $matches) === 1) {
            $code = strtoupper($matches[1]);

            return match ($code) {
                'UK' => 'GB',
                default => $code,
            };
        }

        return '';
    }
}
