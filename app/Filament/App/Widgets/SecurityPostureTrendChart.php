<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\ChartWidget;

class SecurityPostureTrendChart extends ChartWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected ?string $heading = 'Security Posture Trend';

    protected ?string $description = 'Block ratio trend for the last 7 days.';

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

        $labels = (array) ($metrics?->trend_labels ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
        $allowed = array_map('floatval', (array) ($metrics?->allowed_trend ?? [0, 0, 0, 0, 0, 0, 0]));
        $blocked = array_map('floatval', (array) ($metrics?->blocked_trend ?? [0, 0, 0, 0, 0, 0, 0]));

        $ratios = [];
        $points = max(count($allowed), count($blocked), count($labels));
        for ($i = 0; $i < $points; $i++) {
            $a = (float) ($allowed[$i] ?? 0);
            $b = (float) ($blocked[$i] ?? 0);
            $total = $a + $b;
            $ratios[] = $total > 0 ? round(($b / $total) * 100, 2) : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Block ratio %',
                    'data' => $ratios,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}

