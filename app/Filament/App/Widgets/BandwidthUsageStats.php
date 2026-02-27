<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\BandwidthUsageService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BandwidthUsageStats extends StatsOverviewWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Bandwidth Usage';

    protected ?string $description = 'Current month usage versus included plan allowance.';

    protected function getStats(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $usage = app(BandwidthUsageService::class)->forSite($site);
        $percent = (float) $usage['percent_used'];
        $warning = (bool) $usage['warning'];

        return [
            Stat::make('Used This Month', number_format((float) $usage['usage_gb'], 2).' GB')
                ->description('Across protected traffic')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($warning ? 'warning' : 'success'),
            Stat::make('Included', number_format((int) $usage['included_gb']).' GB')
                ->description('Plan allowance')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('gray'),
            Stat::make('Usage', number_format($percent, 2).'%')
                ->description($warning ? 'You are close to your included usage.' : 'Within included usage.')
                ->descriptionIcon($warning ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($warning ? 'warning' : 'success'),
        ];
    }
}
