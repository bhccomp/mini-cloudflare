<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\ChartWidget;

class RegionalThreatLevelChart extends ChartWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $heading = 'Regional Threat Profile';

    protected ?string $description = 'Relative threat pressure by region.';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'radar';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Threat score',
                    'data' => [22, 34, 29, 12, 41],
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.2)',
                    'pointBackgroundColor' => '#fb923c',
                ],
            ],
            'labels' => ['North America', 'Europe', 'Asia Pacific', 'South America', 'Other'],
        ];
    }
}
