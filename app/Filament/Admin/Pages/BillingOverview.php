<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

class BillingOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $title = 'Billing Overview';

    protected string $view = 'filament.admin.pages.billing-overview';
}
