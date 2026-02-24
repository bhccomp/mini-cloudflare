<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteSignalsStats extends StatsOverviewWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Security Signals';

    protected ?string $description = 'Live overview of traffic, certificate, and edge health.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $site->loadMissing('analyticsMetric');
        $metrics = $site->analyticsMetric;

        $blocked = $metrics?->blocked_requests_24h !== null
            ? number_format((int) $metrics->blocked_requests_24h)
            : 'Coming soon';
        $cacheHitRatio = $metrics?->cache_hit_ratio !== null
            ? number_format((float) $metrics->cache_hit_ratio, 2).'%'
            : 'Coming soon';

        return [
            Stat::make('Blocked Requests (24h)', $blocked)
                ->description('Firewall activity')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->chart($metrics?->blocked_trend ?: [0, 0, 0, 0, 0, 0, 0])
                ->color('danger'),
            Stat::make('Cache Hit Ratio', $cacheHitRatio)
                ->description('Edge cache efficiency')
                ->descriptionIcon('heroicon-m-bolt')
                ->chart($this->cacheRatioTrend($metrics?->cache_hit_ratio))
                ->color('success'),
            Stat::make('Certificate', $site->acm_certificate_arn ? 'Issued / Pending' : 'Not requested')
                ->description('TLS readiness')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($site->acm_certificate_arn ? 'success' : 'warning'),
            Stat::make('Distribution', $site->cloudfront_distribution_id ? 'Healthy / Provisioning' : 'Not deployed')
                ->description('Edge delivery state')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($site->cloudfront_distribution_id ? 'success' : 'gray'),
        ];
    }

    protected function cacheRatioTrend(?float $current): array
    {
        if ($current === null) {
            return [0, 0, 0, 0, 0, 0, 0];
        }

        return [
            max(0, $current - 4),
            max(0, $current - 2),
            max(0, $current - 3),
            max(0, $current - 1),
            $current,
            min(100, $current + 1),
            min(100, $current + 2),
        ];
    }
}
