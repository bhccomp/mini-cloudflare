<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use Filament\Widgets\Widget;

class TrafficRegionsWidget extends Widget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.traffic-regions-widget';

    protected function getViewData(): array
    {
        return [
            'regions' => [
                ['name' => 'North America', 'share' => 44, 'threat' => 'Low'],
                ['name' => 'Europe', 'share' => 31, 'threat' => 'Medium'],
                ['name' => 'Asia Pacific', 'share' => 19, 'threat' => 'Medium'],
                ['name' => 'South America', 'share' => 4, 'threat' => 'Low'],
                ['name' => 'Other', 'share' => 2, 'threat' => 'Elevated'],
            ],
        ];
    }
}
