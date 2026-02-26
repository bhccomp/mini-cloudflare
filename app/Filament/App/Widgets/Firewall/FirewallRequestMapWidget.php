<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Widgets\Widget;

class FirewallRequestMapWidget extends Widget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.firewall.request-map';

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getMapPoints(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $insights = app(FirewallInsightsPresenter::class)->insights($site);

        return app(FirewallInsightsPresenter::class)->mapPoints($insights);
    }

    protected function getViewData(): array
    {
        return [
            'points' => $this->getMapPoints(),
        ];
    }

    /**
     * Compatibility no-op for stale Livewire chart update calls after switching
     * this component from ChartWidget to a custom Widget implementation.
     */
    public function updateChartData(): void {}
}
