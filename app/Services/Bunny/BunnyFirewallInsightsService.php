<?php

namespace App\Services\Bunny;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;

class BunnyFirewallInsightsService
{
    public function __construct(protected BunnyLogsService $logs) {}

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
        $events = collect($this->logs->recentLogs($site, 300));

        if ($events->isEmpty()) {
            return [
                'summary' => ['total' => 0, 'blocked' => 0, 'allowed' => 0, 'counted' => 0, 'block_ratio' => 0],
                'top_countries' => [],
                'top_ips' => [],
                'events' => [],
                'captured_at' => now(),
                'source' => 'bunny_live',
                'message' => 'No Bunny log events available yet.',
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
}
