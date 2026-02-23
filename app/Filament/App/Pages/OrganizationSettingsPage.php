<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;

class OrganizationSettingsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?string $title = 'Organization Settings';

    protected string $view = 'filament.app.pages.organization-settings-page';
}
