<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

class GlobalMonitoring extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $title = 'Global Monitoring';

    protected string $view = 'filament.admin.pages.global-monitoring';
}
