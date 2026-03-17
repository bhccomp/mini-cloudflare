<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\AvailabilityMonitorService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AvailabilityStatusStats extends StatsOverviewWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Availability Monitoring';

    protected ?string $description = 'Latest outside-in reachability checks for the selected site.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $latest = $site->availabilityChecks()->latest('checked_at')->first();
        $cadence = app(AvailabilityMonitorService::class)->intervalLabelForSite($site);

        return [
            Stat::make('Status', $latest?->status === 'up' ? 'Up' : ($latest?->status === 'down' ? 'Down' : 'Not checked'))
                ->description($latest?->checked_at ? 'Last checked '.$latest->checked_at->diffForHumans() : 'No monitor checks yet')
                ->descriptionIcon('heroicon-m-heart')
                ->color(match ($latest?->status) {
                    'up' => 'success',
                    'down' => 'danger',
                    default => 'gray',
                }),
            Stat::make('Latency', $latest?->latency_ms ? "{$latest->latency_ms} ms" : '--')
                ->description('Most recent response time')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
            Stat::make('Cadence', $cadence)
                ->description('Planned monitor frequency')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
            Stat::make('HTTP', $latest?->status_code ? (string) $latest->status_code : '--')
                ->description($latest?->error_message ? (string) $latest->error_message : 'Latest monitor response code')
                ->descriptionIcon('heroicon-m-signal')
                ->color($latest?->status === 'down' ? 'danger' : 'gray'),
        ];
    }
}
