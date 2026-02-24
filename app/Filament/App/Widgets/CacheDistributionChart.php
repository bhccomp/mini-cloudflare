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
        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => [68, 32],
                    'backgroundColor' => ['#14b8a6', '#6366f1'],
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => ['Cached', 'Origin'],
        ];
    }
}
