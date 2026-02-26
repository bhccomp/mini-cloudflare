<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Widgets\ChartWidget;

class FirewallAttackBreakdownChart extends ChartWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Attack Breakdown';

    protected ?string $description = 'Allowed vs suspicious vs blocked requests.';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [
                'datasets' => [[
                    'data' => [0, 0, 0],
                    'backgroundColor' => ['#22c55e', '#f59e0b', '#ef4444'],
                ]],
                'labels' => ['Allowed', 'Suspicious', 'Blocked'],
            ];
        }

        $insights = app(FirewallInsightsPresenter::class)->insights($site);
        $summary = (array) data_get($insights, 'summary', []);
        $allowed = (int) ($summary['allowed'] ?? 0);
        $suspicious = app(FirewallInsightsPresenter::class)->suspiciousRequests($insights);
        $blocked = (int) ($summary['blocked'] ?? 0);
        $hasTraffic = ($allowed + $suspicious + $blocked) > 0;

        return [
            'datasets' => [[
                'data' => [
                    $hasTraffic ? $allowed : 1,
                    $suspicious,
                    $blocked,
                ],
                'backgroundColor' => $hasTraffic
                    ? ['#22c55e', '#f59e0b', '#ef4444']
                    : ['#9ca3af', '#f59e0b', '#ef4444'],
                'hoverOffset' => 6,
            ]],
            'labels' => $hasTraffic
                ? ['Allowed', 'Suspicious', 'Blocked']
                : ['No traffic yet', 'Suspicious', 'Blocked'],
        ];
    }
}
