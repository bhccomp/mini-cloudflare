<?php

namespace App\Filament\Admin\Resources\OrganizationResource\Pages;

use App\Filament\Admin\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Billing\OrganizationCheckoutLinkService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneratePaymentLink extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = OrganizationResource::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.admin.resources.organization-resource.pages.generate-payment-link';

    public Organization $record;

    public ?array $data = [];

    public ?string $generatedUrl = null;

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public function mount(Organization $record): void
    {
        $this->record = $record;

        $this->form->fill([
            'plan_id' => $this->defaultPlanId(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Generate Stripe payment link')
                    ->description('Create a Stripe subscription link for this account before site onboarding begins. Once the customer pays, later site onboarding can continue without sending them back through checkout.')
                    ->schema([
                        Placeholder::make('organization')
                            ->label('Organization')
                            ->content($this->record->name),
                        Placeholder::make('billing_email')
                            ->label('Billing email')
                            ->content($this->record->billing_email ?: 'No billing email set on the organization'),
                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn () => Plan::query()
                                ->where('is_active', true)
                                ->where('is_contact_only', false)
                                ->whereNotNull('stripe_monthly_price_id')
                                ->orderBy('sort_order')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('This creates a monthly Stripe subscription link for the selected plan.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();
        $plan = Plan::query()->findOrFail((int) ($state['plan_id'] ?? 0));

        $this->generatedUrl = app(OrganizationCheckoutLinkService::class)
            ->createSubscriptionCheckoutUrl($this->record, $plan);

        Notification::make()
            ->title('Stripe payment link generated.')
            ->success()
            ->send();
    }

    protected function defaultPlanId(): ?int
    {
        return (int) (Plan::query()
            ->where('is_active', true)
            ->where('is_contact_only', false)
            ->whereNotNull('stripe_monthly_price_id')
            ->orderBy('sort_order')
            ->value('id') ?? 0) ?: null;
    }
}
