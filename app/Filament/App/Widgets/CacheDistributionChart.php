<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\ChartWidget;

class CacheDistributionChart extends ChartWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $heading = 'Cache Delivery Split';

    protected ?string $description = 'How much traffic is served from edge cache vs origin.';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $site = $this->getSelectedSite();
        $site?->loadMissing('analyticsMetric');
        $metrics = $site?->analyticsMetric;

        $cached = $metrics?->cached_requests_24h;
        $origin = $metrics?->origin_requests_24h;

        if ($cached === null || $origin === null) {
            $cached = 0;
            $origin = 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => [(int) $cached, (int) $origin],
                    'backgroundColor' => ['#14b8a6', '#6366f1'],
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => ['Cached', 'Origin'],
        ];
    }
}
