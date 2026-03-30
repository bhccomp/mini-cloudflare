<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\Firewall\FirewallAttackBreakdownChart;
use App\Filament\App\Widgets\Firewall\FirewallRecentEventsTable;
use App\Filament\App\Widgets\Firewall\FirewallRequestMapWidget;
use App\Filament\App\Widgets\Firewall\FirewallThreatSummaryStats;
use App\Filament\App\Widgets\Firewall\FirewallTopCountriesTable;
use App\Filament\App\Widgets\Firewall\FirewallTopIpsTable;
use App\Models\Site;
use App\Services\Analytics\AnalyticsSyncManager;
use App\Services\Bunny\BunnyLogsService;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Actions\Action;
use Illuminate\View\View;

class FirewallPage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'Security & Protection';

    protected static ?string $slug = 'firewall';

    protected static ?int $navigationSort = -40;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Protection Overview';

    protected string $view = 'filament.app.pages.protection.firewall';

    public function getHeader(): ?View
    {
        return view('filament.app.pages.protection.page-header-with-routing-warning');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('range24h')
                ->label('24 hours')
                ->color(fn (): string => $this->firewallRange() === '24h' ? 'primary' : 'gray')
                ->url(fn (): string => static::getUrl([
                    'site_id' => $this->site?->id,
                    'range' => '24h',
                ]))
                ->disabled(fn (): bool => ! $this->site),
            Action::make('range7d')
                ->label('7 days')
                ->color(fn (): string => $this->firewallRange() === '7d' ? 'primary' : 'gray')
                ->url(fn (): string => static::getUrl([
                    'site_id' => $this->site?->id,
                    'range' => '7d',
                ]))
                ->disabled(fn (): bool => ! $this->site),
            Action::make('syncNow')
                ->label('Sync now')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->action('syncFirewallNow')
                ->disabled(fn (): bool => ! $this->site),
            Action::make('troubleshootingMode')
                ->label(fn (): string => $this->isTroubleshootingMode()
                    ? 'Disable Troubleshooting Mode'
                    : 'Enable Troubleshooting Mode')
                ->icon('heroicon-m-wrench-screwdriver')
                ->color(fn (): string => $this->isTroubleshootingMode() ? 'warning' : 'gray')
                ->action('toggleTroubleshootingMode')
                ->disabled(fn (): bool => ! $this->site),
            Action::make('accessControl')
                ->label('Open WAF')
                ->icon('heroicon-m-no-symbol')
                ->color('gray')
                ->url(fn (): string => FirewallAccessControlPage::getUrl(['site_id' => $this->site?->id]))
                ->disabled(fn (): bool => ! $this->site),
            Action::make('shieldSettings')
                ->label('Open DDoS')
                ->icon('heroicon-m-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => FirewallShieldSettingsPage::getUrl(['site_id' => $this->site?->id]))
                ->disabled(fn (): bool => ! $this->site),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->site) {
            return [];
        }

        if ($this->isSimpleMode()) {
            return [];
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

        if (! $this->ensureNotDemoReadOnly('Firewall sync')) {
            return;
        }

        $this->throttle('firewall-sync');

        app(FirewallInsightsPresenter::class)->forget($this->site);
        app(AnalyticsSyncManager::class)->syncSiteMetrics($this->site);
        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            app(BunnyLogsService::class)->syncToLocalStore($this->site, $this->firewallRange() === '7d' ? 5000 : 500, $this->firewallRange());
        }

        $this->refreshSite();
        $this->dispatch('firewall-sync-widgets');

        $insights = app(FirewallInsightsPresenter::class)->insights($this->site, $this->firewallRange());
        $total = (int) data_get($insights, 'summary.total', 0);
        $blocked = (int) data_get($insights, 'summary.blocked', 0);

        if ($total === 0) {
            $this->notify('Sync complete. Edge telemetry reports 0 requests in the selected time range.');

            return;
        }

        $this->notify('Sync complete. '.$total.' requests observed, '.$blocked.' blocked for the last '.$this->firewallRangeLabel().'.');
    }
}
