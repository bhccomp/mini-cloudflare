<?php

namespace App\Filament\App\Pages;

class OriginPage extends BaseProtectionPage
{
    protected static ?string $slug = 'origin';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Origin';

    protected static ?string $title = 'Origin';

    protected string $view = 'filament.app.pages.protection.origin';
}
