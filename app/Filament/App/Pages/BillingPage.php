<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;

class BillingPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?string $title = 'Billing';

    protected string $view = 'filament.app.pages.billing-page';
}
