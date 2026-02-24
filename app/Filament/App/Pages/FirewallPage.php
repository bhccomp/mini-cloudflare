<?php

namespace App\Filament\App\Pages;

class FirewallPage extends BaseProtectionPage
{
    protected static ?string $slug = 'firewall';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Firewall';

    protected static ?string $title = 'Firewall';

    protected string $view = 'filament.app.pages.protection.firewall';
}
