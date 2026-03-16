<?php

namespace App\Filament\App\Widgets\WordPress;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\WordPress\PluginSiteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WordPressConnectionStats extends StatsOverviewWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Plugin Connection';

    protected ?string $description = 'Connection state, paid access, and current WordPress environment details.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site || ! $site->pluginConnection) {
            return [];
        }

        $plugin = app(PluginSiteService::class);
        $siteMeta = $plugin->wordpressSiteMetaForSite($site);
        $billing = $plugin->billingAccessSummaryForSite($site);
        $lastSeen = $site->pluginConnection->last_seen_at?->diffForHumans() ?? 'Never';

        return [
            Stat::make('Connection', ucfirst((string) ($site->pluginConnection->status ?: 'connected')))
                ->description('Last seen '.$lastSeen)
                ->descriptionIcon('heroicon-m-link')
                ->color('success'),
            Stat::make('Paid Access', $billing['pro_enabled'] ? 'Enabled' : 'Plan Required')
                ->description((string) $billing['message'])
                ->descriptionIcon($billing['pro_enabled'] ? 'heroicon-m-shield-check' : 'heroicon-m-credit-card')
                ->color($billing['pro_enabled'] ? 'success' : 'warning'),
            Stat::make('WordPress', $siteMeta['wp_version'] ?: '--')
                ->description('Reported by connected plugin')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('gray'),
            Stat::make('Plugin', $siteMeta['plugin_version'] ?: '--')
                ->description('FirePhage Security version')
                ->descriptionIcon('heroicon-m-command-line')
                ->color('gray'),
        ];
    }
}
