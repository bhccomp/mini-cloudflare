<?php

namespace App\Filament\App\Pages;

class AnalyticsPage extends BaseProtectionPage
{
    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics';

    protected string $view = 'filament.app.pages.protection.analytics';
}
