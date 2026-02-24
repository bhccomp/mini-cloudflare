<?php

namespace App\Filament\App\Pages;

class LogsPage extends BaseProtectionPage
{
    protected static ?string $slug = 'logs';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $title = 'Logs';

    protected string $view = 'filament.app.pages.protection.logs';
}
