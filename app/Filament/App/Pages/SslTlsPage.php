<?php

namespace App\Filament\App\Pages;

class SslTlsPage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'Security & Protection';

    protected static ?string $slug = 'ssl';

    protected static ?int $navigationSort = -30;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'SSL/TLS';

    protected static ?string $title = 'SSL/TLS';

    protected string $view = 'filament.app.pages.protection.ssl';
}
