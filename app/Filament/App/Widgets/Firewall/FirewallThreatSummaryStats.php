<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FirewallThreatSummaryStats extends StatsOverviewWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Threat Summary';

    protected ?string $description = 'Live firewall posture and request pressure over the last 24 hours.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $site->loadMissing('analyticsMetric');
        $insights = app(FirewallInsightsPresenter::class)->insights($site);
        $summary = (array) data_get($insights, 'summary', []);
        $threatLevel = app(FirewallInsightsPresenter::class)->threatLevel($insights);
        $suspicious = app(FirewallInsightsPresenter::class)->suspiciousRequests($insights);

        $threatColor = match ($threatLevel) {
            'Under Attack' => 'danger',
            'Degraded' => 'warning',
            default => 'success',
        };

        return [
            Stat::make('Threat Level', $threatLevel)
                ->description('Based on block ratio')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($threatColor),
            Stat::make('Total Requests (24h)', number_format((int) ($summary['total'] ?? 0)))
                ->description('All observed requests')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
            Stat::make('Blocked (24h)', number_format((int) ($summary['blocked'] ?? 0)))
                ->description('Denied or challenged')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger'),
            Stat::make('Suspicious (24h)', number_format($suspicious))
                ->description('Potential risk signals')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
            Stat::make('Last Sync', $site->analyticsMetric?->captured_at?->diffForHumans() ?: 'No sync yet')
                ->description('Telemetry freshness')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
