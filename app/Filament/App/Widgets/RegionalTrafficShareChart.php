<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\ChartWidget;

class RegionalTrafficShareChart extends ChartWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $heading = 'Regional Traffic Share';

    protected ?string $description = 'Traffic distribution by geography.';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Request share %',
                    'data' => [44, 31, 19, 4, 2],
                    'backgroundColor' => [
                        '#0ea5e9',
                        '#22d3ee',
                        '#06b6d4',
                        '#818cf8',
                        '#94a3b8',
                    ],
                    'borderRadius' => 8,
                ],
            ],
            'labels' => ['North America', 'Europe', 'Asia Pacific', 'South America', 'Other'],
        ];
    }
}
