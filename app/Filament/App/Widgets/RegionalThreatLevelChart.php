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
        $site = $this->getSelectedSite();
        $site?->loadMissing('analyticsMetric');
        $regions = (array) ($site?->analyticsMetric?->regional_threat ?? []);

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
                    'label' => 'Threat score',
                    'data' => array_values($regions),
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.2)',
                    'pointBackgroundColor' => '#fb923c',
                ],
            ],
            'labels' => array_keys($regions),
        ];
    }
}
