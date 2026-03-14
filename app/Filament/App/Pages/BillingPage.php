<?php

namespace App\Filament\App\Pages;

use App\Models\Organization;
use App\Services\Billing\OrganizationBillingService;
use App\Services\OrganizationAccessService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class BillingPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'billing';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?string $title = 'Billing';

    protected string $view = 'filament.app.pages.billing-page';

    public ?array $data = [];

    public ?Organization $organization = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $organization = app(OrganizationBillingService::class)->currentOrganizationForUser();

        if (! $user || ! $organization) {
            return false;
        }

        return app(OrganizationAccessService::class)->can(
            $user,
            $organization,
            OrganizationAccessService::PERMISSION_BILLING_READ,
        );
    }

    public function mount(): void
    {
        $this->organization = app(OrganizationBillingService::class)->currentOrganizationForUser();

        abort_unless($this->organization !== null, 403);
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'billing_email' => $this->organization->billing_email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('billing_email')
                    ->label('Billing email')
                    ->email()
                    ->required()
                    ->helperText('Stripe invoices, receipts, and customer portal actions will use this address.'),
            ])
            ->statePath('data');
    }

    public function saveBillingProfile(): void
    {
        if (! $this->organization) {
            return;
        }

        $state = $this->form->getState();

        $this->organization->forceFill([
            'billing_email' => strtolower(trim((string) data_get($state, 'billing_email'))),
        ])->save();

        if (app(OrganizationBillingService::class)->hasStripeConfigured()) {
            app(OrganizationBillingService::class)->ensureStripeCustomer($this->organization->fresh());
            $this->organization->refresh();
        }

        Notification::make()
            ->title('Billing profile updated.')
            ->success()
            ->send();
    }

    public function openCustomerPortal()
    {
        if (! $this->organization) {
            return null;
        }

        try {
            $url = app(OrganizationBillingService::class)->createCustomerPortalUrl(
                $this->organization->fresh(),
                static::getUrl(),
            );

            return redirect()->away($url);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Unable to open Stripe customer portal.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function invoices(): Collection
    {
        try {
            return app(OrganizationBillingService::class)->invoices($this->organization);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Unable to load invoices from Stripe.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return collect();
        }
    }

    public function subscription()
    {
        return app(OrganizationBillingService::class)->currentSubscription($this->organization);
    }

    public function hasStripeConfigured(): bool
    {
        return app(OrganizationBillingService::class)->hasStripeConfigured();
    }

    public function hasStripeCustomer(): bool
    {
        return (bool) ($this->organization?->stripe_customer_id
            ?? app(OrganizationBillingService::class)->resolveCustomerId($this->organization));
    }
}
