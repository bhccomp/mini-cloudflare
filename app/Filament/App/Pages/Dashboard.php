<?php

namespace App\Filament\App\Pages;

class Dashboard extends BaseProtectionPage
{
    protected static ?string $slug = 'overview';

    protected static ?int $navigationSort = -2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Overview';

    protected string $view = 'filament.app.pages.dashboard';
}
