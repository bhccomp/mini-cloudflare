<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EdgeServiceStatusStats extends StatsOverviewWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Edge Services';

    protected ?string $description = 'SSL, delivery, cache, and origin hardening status for the selected site.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $certificateStatus = $site->provider === \App\Models\Site::PROVIDER_BUNNY
            ? match ($site->onboarding_status) {
                \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING => 'DNS OK, SSL pending',
                \App\Models\Site::ONBOARDING_LIVE => 'Active',
                default => 'Pending',
            }
            : (! $site->acm_certificate_arn
                ? 'Not requested'
                : ($site->status === \App\Models\Site::STATUS_ACTIVE ? 'Issued' : 'Pending validation'));

        $distributionHealth = ! $site->cloudfront_distribution_id
            ? 'Not deployed'
            : ($site->status === \App\Models\Site::STATUS_ACTIVE ? 'Healthy' : 'Provisioning');

        $cacheMode = ucfirst((string) data_get($site->required_dns_records, 'control_panel.cache_mode', 'standard'));
        $originProtection = (bool) data_get($site->required_dns_records, 'control_panel.origin_lockdown', false);

        return [
            Stat::make('SSL', $certificateStatus)
                ->description('Certificate and HTTPS readiness')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color(str_contains(strtolower($certificateStatus), 'active') || str_contains(strtolower($certificateStatus), 'issued') ? 'success' : 'warning'),
            Stat::make('CDN', $distributionHealth)
                ->description('Edge delivery status')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($distributionHealth === 'Healthy' ? 'success' : 'warning'),
            Stat::make('Cache', $cacheMode)
                ->description('Current edge cache mode')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($cacheMode === 'Aggressive' ? 'success' : 'primary'),
            Stat::make('Origin Lockdown', $originProtection ? 'Enabled' : 'Pending')
                ->description('Direct origin exposure hardening')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($originProtection ? 'success' : 'warning'),
        ];
    }
}
