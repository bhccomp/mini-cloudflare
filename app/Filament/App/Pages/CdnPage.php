<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Services\Analytics\AnalyticsSyncManager;
use App\Services\BandwidthUsageService;
use App\Services\Bunny\BunnyLogsService;

class CdnPage extends BaseProtectionPage
{
    protected static ?string $slug = 'cdn';

    protected static ?int $navigationSort = 0;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'CDN';

    protected static ?string $title = 'CDN';

    protected string $view = 'filament.app.pages.protection.cdn';

    public function refreshCdnMetrics(): void
    {
        if (! $this->site) {
            return;
        }

        app(AnalyticsSyncManager::class)->syncSiteMetrics($this->site);
        $this->refreshSite();
        $this->notify('CDN metrics refreshed');
    }

    public function cdnActionPrefix(): string
    {
        return $this->site?->provider === \App\Models\Site::PROVIDER_BUNNY ? 'edge.' : 'cloudfront.';
    }

    public function requestsTrend(): array
    {
        $labels = (array) ($this->site?->analyticsMetric?->trend_labels ?? []);
        $allowed = (array) ($this->site?->analyticsMetric?->allowed_trend ?? []);
        $blocked = (array) ($this->site?->analyticsMetric?->blocked_trend ?? []);

        return collect($labels)->map(function (string $label, int $index) use ($allowed, $blocked): array {
            $requests = (int) ($allowed[$index] ?? 0) + (int) ($blocked[$index] ?? 0);

            return [
                'label' => $label,
                'requests' => $requests,
            ];
        })->all();
    }

    public function bandwidthTrend(): array
    {
        $requests = $this->requestsTrend();

        return collect($requests)->map(function (array $row): array {
            return [
                'label' => (string) $row['label'],
                'bandwidth_mb' => round(((int) $row['requests']) * 0.34, 2),
            ];
        })->all();
    }

    public function originOffloadRatio(): float
    {
        $cached = (int) ($this->site?->analyticsMetric?->cached_requests_24h ?? 0);
        $origin = (int) ($this->site?->analyticsMetric?->origin_requests_24h ?? 0);
        $total = $cached + $origin;

        if ($total <= 0) {
            return 0.0;
        }

        return round(($cached / $total) * 100, 2);
    }

    /**
     * @return array{usage_gb: float, included_gb: int, percent_used: float, warning: bool, plan_code: string}
     */
    public function bandwidthUsageSummary(): array
    {
        if (! $this->site) {
            return [
                'usage_gb' => 0.0,
                'included_gb' => 0,
                'percent_used' => 0.0,
                'warning' => false,
                'plan_code' => 'default',
            ];
        }

        return app(BandwidthUsageService::class)->forSite($this->site);
    }

    public function topCachedPaths(): array
    {
        if (! $this->site) {
            return [];
        }

        if ($this->site->provider !== Site::PROVIDER_BUNNY) {
            return [];
        }

        $rows = app(BunnyLogsService::class)->recentLogs($this->site, 400);

        return collect($rows)
            ->filter(fn (array $row): bool => (int) ($row['status_code'] ?? 0) < 400)
            ->groupBy(fn (array $row): string => (string) ($row['uri'] ?? '/'))
            ->map(function ($group, string $path): array {
                $hits = $group->count();
                $bytes = (int) $group->sum('bytes');

                return [
                    'path' => $path,
                    'hits' => $hits,
                    'bandwidth_mb' => round($bytes / 1048576, 2),
                ];
            })
            ->sortByDesc('hits')
            ->take(10)
            ->values()
            ->all();
    }
}
