<?php

namespace App\Filament\App\Pages;

class CdnPage extends BaseProtectionPage
{
    protected static ?string $slug = 'cdn';

    protected static ?int $navigationSort = 0;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'CDN';

    protected static ?string $title = 'CDN';

    protected string $view = 'filament.app.pages.protection.cdn';
}
