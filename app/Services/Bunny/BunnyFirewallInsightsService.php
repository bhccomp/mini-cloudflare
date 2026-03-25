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
        // Prefer a larger live Bunny sample so top countries / IPs reflect recent edge traffic
        // instead of a small or stale local subset.
        $events = collect($this->logs->recentLogs($site, 2000));

        if ($events->isEmpty()) {
            $events = $this->localEvents($site, 2000);
        }

        if ($events->isEmpty()) {
            $synced = $this->logs->syncToLocalStore($site, 1000);

            if ($synced > 0) {
                $events = $this->localEvents($site, 2000);
            }
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
        $metric = $site->analyticsMetric ?: $this->analytics->syncSiteMetrics($site);
        $isDemoSeed = (bool) data_get($site->provider_meta, 'demo_seeded', false);
        $suspicious = null;
        $blockRatioOverride = null;
        $suspiciousRatioOverride = null;

        // Demo/screenshot sites should reflect seeded analytics totals on summary cards.
        if ($isDemoSeed && $metric) {
            $total = (int) ($metric->total_requests_24h ?? $total);
            $blocked = (int) ($metric->blocked_requests_24h ?? $blocked);
            $allowed = max(0, (int) ($metric->allowed_requests_24h ?? ($total - $blocked)));
            $suspicious = (int) data_get($site->provider_meta, 'demo_suspicious_requests_24h', 0);
            $blockRatioOverride = data_get($site->provider_meta, 'demo_block_ratio');
            $suspiciousRatioOverride = data_get($site->provider_meta, 'demo_suspicious_ratio');
        }

        $topCountries = $events
            ->groupBy('country')
            ->map(fn ($group, string $country): array => ['country' => $country, 'requests' => $group->count()])
            ->sortByDesc('requests')
            ->take(10)
            ->values()
            ->all();

        if ($isDemoSeed) {
            $demoTopCountries = (array) data_get($site->provider_meta, 'demo_top_countries', []);

            if ($demoTopCountries !== []) {
                $topCountries = collect($demoTopCountries)
                    ->map(function (array $row): array {
                        return [
                            'country' => strtoupper((string) ($row['country'] ?? 'US')),
                            'requests' => max(0, (int) ($row['requests'] ?? 0)),
                        ];
                    })
                    ->filter(fn (array $row): bool => $row['country'] !== '')
                    ->values()
                    ->all();
            }
        }

        $topIps = $events
            ->groupBy('ip')
            ->map(function ($group, string $ip): array {
                $country = (string) collect($group)
                    ->groupBy(fn (array $event): string => strtoupper((string) ($event['country'] ?? '')))
                    ->sortByDesc(fn ($rows) => count($rows))
                    ->keys()
                    ->first();

                return [
                    'ip' => $ip,
                    'country' => $country !== '' ? $country : '??',
                    'requests' => $group->count(),
                    'blocked' => $group->filter(fn (array $event): bool => in_array(strtoupper((string) ($event['action'] ?? 'ALLOW')), ['BLOCK', 'DENY', 'CHALLENGE', 'CAPTCHA'], true) || (int) ($event['status_code'] ?? 200) === 403)->count(),
                ];
            })
            ->sortByDesc('requests')
            ->take(10)
            ->values()
            ->all();

        if ($isDemoSeed) {
            $demoTopIps = (array) data_get($site->provider_meta, 'demo_top_ips', []);

            if ($demoTopIps !== []) {
                $topIps = collect($demoTopIps)
                    ->map(function (array $row): array {
                        return [
                            'ip' => (string) ($row['ip'] ?? ''),
                            'country' => strtoupper((string) ($row['country'] ?? '??')),
                            'requests' => max(0, (int) ($row['requests'] ?? 0)),
                            'blocked' => max(0, (int) ($row['blocked'] ?? 0)),
                        ];
                    })
                    ->filter(fn (array $row): bool => $row['ip'] !== '')
                    ->values()
                    ->all();
            }
        }

        return [
            'summary' => [
                'total' => $total,
                'blocked' => $blocked,
                'allowed' => $allowed,
                'suspicious' => $suspicious,
                'counted' => 0,
                'block_ratio' => is_numeric($blockRatioOverride)
                    ? (float) $blockRatioOverride
                    : ($total > 0 ? round(($blocked / $total) * 100, 2) : 0),
                'suspicious_ratio' => is_numeric($suspiciousRatioOverride)
                    ? (float) $suspiciousRatioOverride
                    : null,
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

    protected function localEvents(Site $site, int $limit = 500): \Illuminate\Support\Collection
    {
        return EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->where('event_at', '>=', now()->subDays(7))
            ->latest('event_at')
            ->limit($limit)
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

            $usStates = [
                'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
                'HI', 'IA', 'ID', 'IL', 'IN', 'KS', 'KY', 'LA', 'MA', 'MD',
                'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NC', 'ND', 'NE', 'NH',
                'NJ', 'NM', 'NV', 'NY', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
                'SD', 'TN', 'TX', 'UT', 'VA', 'VT', 'WA', 'WI', 'WV', 'WY',
                'DC',
            ];

            if (in_array($code, $usStates, true)) {
                return 'US';
            }

            return match ($code) {
                'UK' => 'GB',
                default => $code,
            };
        }

        return '';
    }
}
