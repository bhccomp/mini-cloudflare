<?php

namespace App\Filament\App\Pages;

class CachePage extends BaseProtectionPage
{
    protected static ?string $slug = 'cache';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Cache';

    protected static ?string $title = 'Cache';

    protected string $view = 'filament.app.pages.protection.cache';
}
