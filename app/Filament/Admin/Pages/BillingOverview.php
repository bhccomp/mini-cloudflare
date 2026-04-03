<?php

namespace App\Filament\Admin\Pages;

use App\Services\Billing\AdminBillingOverviewService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class BillingOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $title = 'Billing Overview';

    protected string $view = 'filament.admin.pages.billing-overview';

    public function metrics(): array
    {
        return app(AdminBillingOverviewService::class)->headlineMetrics();
    }

    public function organizations(): Collection
    {
        return app(AdminBillingOverviewService::class)->organizationRows();
    }
}
