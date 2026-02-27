<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\BandwidthUsageStats;
use App\Filament\App\Widgets\CacheDistributionChart;
use App\Filament\App\Widgets\RegionalThreatLevelChart;
use App\Filament\App\Widgets\RegionalTrafficShareChart;
use App\Filament\App\Widgets\SimpleActivityFeedTable;
use App\Filament\App\Widgets\SiteSignalsStats;
use App\Filament\App\Widgets\TrafficTrendChart;

class Dashboard extends BaseProtectionPage
{
    protected static ?string $slug = 'overview';

    protected static ?int $navigationSort = -2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Overview';

    protected string $view = 'filament.app.pages.dashboard';

    protected static bool $shouldRegisterNavigation = false;

    protected function getHeaderWidgets(): array
    {
        if (! $this->site) {
            return [];
        }

        if ($this->isSimpleMode()) {
            return [
                SiteSignalsStats::class,
                BandwidthUsageStats::class,
                SimpleActivityFeedTable::class,
            ];
        }

        return [
            SiteSignalsStats::class,
            BandwidthUsageStats::class,
            TrafficTrendChart::class,
            CacheDistributionChart::class,
            RegionalTrafficShareChart::class,
            RegionalThreatLevelChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }
}
