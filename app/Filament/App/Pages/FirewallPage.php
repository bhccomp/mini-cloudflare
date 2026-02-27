<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\Firewall\FirewallAttackBreakdownChart;
use App\Filament\App\Widgets\Firewall\FirewallRecentEventsTable;
use App\Filament\App\Widgets\Firewall\FirewallRequestMapWidget;
use App\Filament\App\Widgets\Firewall\FirewallThreatSummaryStats;
use App\Filament\App\Widgets\Firewall\FirewallTopCountriesTable;
use App\Filament\App\Widgets\Firewall\FirewallTopIpsTable;
use App\Filament\App\Widgets\SimpleActivityFeedTable;
use App\Models\Site;
use App\Services\Analytics\AnalyticsSyncManager;
use App\Services\Bunny\BunnyLogsService;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Actions\Action;

class FirewallPage extends BaseProtectionPage
{
    protected static ?string $slug = 'firewall';

    protected static ?int $navigationSort = -2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Firewall';

    protected static ?string $title = 'Firewall';

    protected string $view = 'filament.app.pages.protection.firewall';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNow')
                ->label('Sync now')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->action('syncFirewallNow')
                ->disabled(fn (): bool => ! $this->site),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->site) {
            return [];
        }

        if ($this->isSimpleMode()) {
            return [
                FirewallThreatSummaryStats::class,
                SimpleActivityFeedTable::class,
            ];
        }

        return [
            FirewallThreatSummaryStats::class,
            FirewallRequestMapWidget::class,
            FirewallTopCountriesTable::class,
            FirewallTopIpsTable::class,
            FirewallAttackBreakdownChart::class,
            FirewallRecentEventsTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        if ($this->isSimpleMode()) {
            return 1;
        }

        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function syncFirewallNow(): void
    {
        if (! $this->site) {
            return;
        }

        $this->throttle('firewall-sync');

        app(FirewallInsightsPresenter::class)->forget($this->site);
        app(AnalyticsSyncManager::class)->syncSiteMetrics($this->site);
        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            app(BunnyLogsService::class)->syncToLocalStore($this->site, 500);
        }

        $this->refreshSite();
        $this->dispatch('firewall-sync-widgets');

        $insights = app(FirewallInsightsPresenter::class)->insights($this->site);
        $total = (int) ($this->site->analyticsMetric?->total_requests_24h
            ?? data_get($insights, 'summary.total', 0));
        $blocked = (int) ($this->site->analyticsMetric?->blocked_requests_24h
            ?? data_get($insights, 'summary.blocked', 0));

        if ($total === 0) {
            $this->notify('Sync complete. Edge telemetry reports 0 requests in the selected time range.');

            return;
        }

        $this->notify('Sync complete. '.$total.' requests observed, '.$blocked.' blocked.');
    }
}
