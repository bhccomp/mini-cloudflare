<?php

namespace App\Filament\Admin\Resources\OrganizationResource\Pages;

use App\Filament\Admin\Resources\OrganizationResource;
use App\Services\Billing\OrganizationBillingService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_payment_link')
                ->label('Generate Stripe link')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->visible(fn (): bool => app(OrganizationBillingService::class)->hasStripeConfigured())
                ->url(fn (): string => OrganizationResource::getUrl('generate-payment-link', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}
