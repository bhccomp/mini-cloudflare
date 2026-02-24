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
        $site = $this->getSelectedSite();
        $site?->loadMissing('analyticsMetric');
        $regions = (array) ($site?->analyticsMetric?->regional_traffic ?? []);

        if ($regions === []) {
            $regions = [
                'North America' => 0,
                'Europe' => 0,
                'Asia Pacific' => 0,
                'South America' => 0,
                'Other' => 0,
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Request share %',
                    'data' => array_values($regions),
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
            'labels' => array_keys($regions),
        ];
    }
}
