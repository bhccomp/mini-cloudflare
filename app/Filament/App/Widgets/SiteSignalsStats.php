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

        $blocked = (string) data_get($site->required_dns_records, 'metrics.blocked_requests_24h', 'Coming soon');
        $cacheHitRatio = (string) data_get($site->required_dns_records, 'metrics.cache_hit_ratio', 'Coming soon');

        return [
            Stat::make('Blocked Requests (24h)', $blocked)
                ->description('Firewall activity')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->chart([6, 12, 10, 18, 14, 20, 16])
                ->color('danger'),
            Stat::make('Cache Hit Ratio', $cacheHitRatio)
                ->description('Edge cache efficiency')
                ->descriptionIcon('heroicon-m-bolt')
                ->chart([42, 48, 45, 54, 59, 63, 66])
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
}
