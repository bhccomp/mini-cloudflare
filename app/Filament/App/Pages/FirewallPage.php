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
            Action::make('toggleUnderAttack')
                ->label('Under Attack Mode')
                ->icon('heroicon-m-shield-exclamation')
                ->color(fn (): string => $this->site?->under_attack ? 'danger' : 'gray')
                ->badge(fn (): string => $this->underAttackModeSupported()
                    ? ($this->site?->under_attack ? 'On' : 'Off')
                    : 'Coming soon')
                ->tooltip(fn (): string => $this->underAttackModeSupported()
                    ? 'Last changed: '.$this->lastAction('waf.')
                    : 'Coming soon for this edge mode.')
                ->requiresConfirmation()
                ->action('toggleUnderAttackMode')
                ->disabled(fn (): bool => ! $this->site || ! $this->underAttackModeSupported()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->site) {
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

        $this->refreshSite();
        $this->dispatch('firewall-sync-widgets');

        $insights = app(FirewallInsightsPresenter::class)->insights($this->site);
        $total = (int) data_get($insights, 'summary.total', 0);
        $blocked = (int) data_get($insights, 'summary.blocked', 0);

        if ($total === 0) {
            $this->notify('Sync complete. Edge telemetry reports 0 requests in the selected time range.');

            return;
        }

        $this->notify('Sync complete. '.$total.' requests observed, '.$blocked.' blocked.');
    }

    public function toggleUnderAttackMode(): void
    {
        if (! $this->site || ! $this->underAttackModeSupported()) {
            return;
        }

        $this->throttle('toggle-under-attack');
        $this->toggleUnderAttack();
        $this->dispatch('firewall-sync-widgets');
    }

    public function underAttackModeSupported(): bool
    {
        return (string) ($this->site?->provider ?? '') === Site::PROVIDER_AWS;
    }
}
