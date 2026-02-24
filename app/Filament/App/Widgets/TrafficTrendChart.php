<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\ChartWidget;

class TrafficTrendChart extends ChartWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $heading = 'Traffic Trend';

    protected ?string $description = '7-day traffic profile for your selected site.';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $site = $this->getSelectedSite();
        $site?->loadMissing('analyticsMetric');
        $metrics = $site?->analyticsMetric;

        $labels = $metrics?->trend_labels ?: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $blocked = $metrics?->blocked_trend ?: [0, 0, 0, 0, 0, 0, 0];
        $allowed = $metrics?->allowed_trend ?: [0, 0, 0, 0, 0, 0, 0];

        return [
            'datasets' => [
                [
                    'label' => 'Blocked',
                    'data' => $blocked,
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.18)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Allowed',
                    'data' => $allowed,
                    'borderColor' => '#22d3ee',
                    'backgroundColor' => 'rgba(34, 211, 238, 0.09)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
