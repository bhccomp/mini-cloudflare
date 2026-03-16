<?php

namespace App\Filament\App\Widgets\WordPress;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\WordPress\PluginSiteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WordPressHealthStats extends StatsOverviewWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Health & Updates';

    protected ?string $description = 'Health checks, checksum posture, and update exposure from the latest plugin report.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site || ! $site->pluginConnection) {
            return [];
        }

        $health = app(PluginSiteService::class)->wordpressHealthSummaryForSite($site);
        $pendingUpdates = $health['core_updates'] + $health['plugin_updates'] + $health['theme_updates'];

        return [
            Stat::make('Checks Passing', number_format($health['good']))
                ->description('Healthy configuration checks')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Warnings', number_format($health['warning']))
                ->description('Review recommended fixes')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
            Stat::make('Critical', number_format($health['critical']))
                ->description($health['checksum_summary'])
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($health['critical'] > 0 ? 'danger' : 'gray'),
            Stat::make('Pending Updates', number_format($pendingUpdates))
                ->description("Core {$health['core_updates']} • Plugins {$health['plugin_updates']} • Themes {$health['theme_updates']}")
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($pendingUpdates > 0 ? 'warning' : 'success'),
        ];
    }
}
