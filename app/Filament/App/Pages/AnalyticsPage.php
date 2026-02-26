<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Services\Analytics\AnalyticsSyncManager;
use App\Services\Bunny\BunnyLogsService;

class AnalyticsPage extends BaseProtectionPage
{
    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics';

    protected string $view = 'filament.app.pages.protection.analytics';

    public function refreshAnalytics(): void
    {
        if (! $this->site) {
            return;
        }

        app(AnalyticsSyncManager::class)->syncSiteMetrics($this->site);
        $this->refreshSite();
        $this->notify('Analytics refreshed');
    }

    public function requestTrend(): array
    {
        $labels = (array) ($this->site?->analyticsMetric?->trend_labels ?? []);
        $allowed = (array) ($this->site?->analyticsMetric?->allowed_trend ?? []);
        $blocked = (array) ($this->site?->analyticsMetric?->blocked_trend ?? []);

        return collect($labels)->map(function (string $label, int $index) use ($allowed, $blocked): array {
            $total = (int) ($allowed[$index] ?? 0) + (int) ($blocked[$index] ?? 0);

            return [
                'label' => $label,
                'total' => $total,
            ];
        })->all();
    }

    public function blockRatioTrend(): array
    {
        $labels = (array) ($this->site?->analyticsMetric?->trend_labels ?? []);
        $allowed = (array) ($this->site?->analyticsMetric?->allowed_trend ?? []);
        $blocked = (array) ($this->site?->analyticsMetric?->blocked_trend ?? []);

        return collect($labels)->map(function (string $label, int $index) use ($allowed, $blocked): array {
            $blockedValue = (int) ($blocked[$index] ?? 0);
            $allowedValue = (int) ($allowed[$index] ?? 0);
            $total = max(1, $blockedValue + $allowedValue);

            return [
                'label' => $label,
                'ratio' => round(($blockedValue / $total) * 100, 2),
            ];
        })->all();
    }

    public function requestsPerHourHeatmap(): array
    {
        $hours = collect(range(0, 23))->mapWithKeys(fn (int $hour): array => [
            str_pad((string) $hour, 2, '0', STR_PAD_LEFT) => 0,
        ]);

        if (! $this->site || $this->site->provider !== Site::PROVIDER_BUNNY) {
            return $hours->map(fn (int $count, string $hour): array => ['hour' => $hour, 'count' => $count])->values()->all();
        }

        $rows = app(BunnyLogsService::class)->recentLogs($this->site, 500);

        $grouped = collect($rows)
            ->groupBy(fn (array $row): string => str_pad((string) ($row['timestamp']->hour ?? 0), 2, '0', STR_PAD_LEFT))
            ->map(fn ($group): int => $group->count());

        return $hours
            ->map(fn (int $count, string $hour): array => [
                'hour' => $hour,
                'count' => (int) ($grouped[$hour] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function telemetryStatus(): string
    {
        if (! $this->site?->analyticsMetric) {
            return 'No data';
        }

        if ($this->site->last_error) {
            return 'Warning';
        }

        return 'Healthy';
    }
}
