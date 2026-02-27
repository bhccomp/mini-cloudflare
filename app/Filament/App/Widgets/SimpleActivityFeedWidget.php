<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\ActivityFeedService;
use Filament\Widgets\Widget;

class SimpleActivityFeedWidget extends Widget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.simple-activity-feed-widget';

    protected function getViewData(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return ['items' => []];
        }

        return [
            'items' => app(ActivityFeedService::class)->forSite($site, 5),
        ];
    }
}
