<?php

namespace App\Filament\App\Pages;

use App\Services\Analytics\AnalyticsSyncManager;

class AnalyticsPage extends BaseProtectionPage
{
    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics';

    protected string $view = 'filament.app.pages.protection.analytics';

    public function refreshAnalytics(): void
    {
        if (! $this->site) {
            return;
        }

        app(AnalyticsSyncManager::class)->syncSiteMetrics($this->site);
        $this->refreshSite();
        $this->notify('Analytics refreshed');
    }
}
