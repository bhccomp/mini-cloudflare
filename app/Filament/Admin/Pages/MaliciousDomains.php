<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

class MaliciousDomains extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Signatures';

    protected static ?string $navigationLabel = 'Malicious Domains';

    protected static ?string $title = 'Malicious Domains';

    protected string $view = 'filament.admin.pages.malicious-domains';
}
