<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;

class SupportPage extends Page
{
    protected static ?string $slug = 'support';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?string $title = 'Support';

    protected string $view = 'filament.app.pages.support-page';

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
