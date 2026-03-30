<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Concerns\InteractsWithFirewallRange;
use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FirewallThreatSummaryStats extends StatsOverviewWidget
{
    use InteractsWithFirewallRange;
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Threat Summary';

    protected ?string $description = 'Live firewall posture and request pressure.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $site->loadMissing('analyticsMetric');
        $insights = app(FirewallInsightsPresenter::class)->insights($site, $this->firewallRange());
        $summary = (array) data_get($insights, 'summary', []);
        $threatLevel = app(FirewallInsightsPresenter::class)->threatLevel($insights);
        $threatColor = match ($threatLevel) {
            'Under Attack' => 'danger',
            'Active Mitigation' => 'warning',
            default => 'success',
        };

        return [
            Stat::make('Threat Level', $threatLevel)
                ->description('Based on block ratio')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($threatColor),
            Stat::make('Total Requests', number_format((int) ($summary['total'] ?? 0)))
                ->description('All observed requests in the last '.$this->firewallRangeLabel())
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
            Stat::make('Blocked', number_format((int) ($summary['blocked'] ?? 0)))
                ->description('Denied or challenged in the last '.$this->firewallRangeLabel())
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger'),
            Stat::make('Challenge Rate', number_format((float) ($summary['challenge_ratio'] ?? 0), 2).'%')
                ->description('Share of requests challenged')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),
            Stat::make('Protection Pressure', number_format((float) ($summary['block_ratio'] ?? 0), 2).'%')
                ->description('Blocked or challenged request share')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
            Stat::make('Last Sync', $site->syncFreshnessForHumans('No sync yet'))
                ->description('Telemetry freshness')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
