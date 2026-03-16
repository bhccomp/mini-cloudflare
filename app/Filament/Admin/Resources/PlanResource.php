<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Models\Plan;
use App\Services\Billing\StripePlanSyncService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Utilities\Get;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Plan Identity')
                ->schema([
                    Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('headline')->maxLength(255),
                    Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                ])->columns(2),
            Section::make('Pricing')
                ->schema([
                    Forms\Components\Toggle::make('is_contact_only')
                        ->label('Contact sales only')
                        ->live()
                        ->default(false),
                    Forms\Components\TextInput::make('monthly_price_cents')
                        ->numeric()
                        ->required(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->hidden(fn (Get $get): bool => (bool) $get('is_contact_only'))
                        ->dehydrated(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->formatStateUsing(fn (?int $state): string => number_format(((int) $state) / 100, 2, '.', ''))
                        ->dehydrateStateUsing(fn ($state): int => (int) round(((float) ($state ?: 0)) * 100))
                        ->default(0)
                        ->prefix('$')
                        ->step('0.01')
                        ->helperText('Enter the monthly price in dollars.'),
                    Forms\Components\TextInput::make('yearly_price_cents')
                        ->numeric()
                        ->required(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->hidden(fn (Get $get): bool => (bool) $get('is_contact_only'))
                        ->dehydrated(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->formatStateUsing(fn (?int $state): string => number_format(((int) $state) / 100, 2, '.', ''))
                        ->dehydrateStateUsing(fn ($state): int => (int) round(((float) ($state ?: 0)) * 100))
                        ->default(0)
                        ->prefix('$')
                        ->step('0.01')
                        ->helperText('Enter the yearly price in dollars.'),
                    Forms\Components\TextInput::make('included_websites')
                        ->label('Included websites')
                        ->numeric()
                        ->default(1)
                        ->required(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->hidden(fn (Get $get): bool => (bool) $get('is_contact_only'))
                        ->dehydrated(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->helperText('How many sites can share one subscription for this plan.'),
                    Forms\Components\TextInput::make('included_requests_per_month')
                        ->label('Included requests / month')
                        ->numeric()
                        ->default(0)
                        ->required(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->hidden(fn (Get $get): bool => (bool) $get('is_contact_only'))
                        ->dehydrated(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->helperText('Monthly requests included before overage billing applies.'),
                    Forms\Components\TextInput::make('overage_block_size')
                        ->label('Overage block size')
                        ->numeric()
                        ->default(1000)
                        ->required(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->hidden(fn (Get $get): bool => (bool) $get('is_contact_only'))
                        ->dehydrated(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->helperText('Requests grouped into one billable overage unit.'),
                    Forms\Components\TextInput::make('overage_price_cents')
                        ->label('Overage price per block')
                        ->numeric()
                        ->default(0)
                        ->required(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->hidden(fn (Get $get): bool => (bool) $get('is_contact_only'))
                        ->dehydrated(fn (Get $get): bool => ! $get('is_contact_only'))
                        ->formatStateUsing(fn (?int $state): string => number_format(((int) $state) / 100, 2, '.', ''))
                        ->dehydrateStateUsing(fn ($state): int => (int) round(((float) ($state ?: 0)) * 100))
                        ->prefix('$')
                        ->step('0.01')
                        ->helperText('Enter the overage price in dollars. Example: 0.02 means $0.02 per block.'),
                    Forms\Components\TextInput::make('currency')
                        ->default('USD')
                        ->required()
                        ->maxLength(3),
                    Forms\Components\TextInput::make('price_suffix')
                        ->default('/ month')
                        ->required(),
                    Forms\Components\TextInput::make('badge')->maxLength(255),
                    Forms\Components\TextInput::make('cta_label')->maxLength(255),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0)->required(),
                    Forms\Components\Toggle::make('is_featured')->default(false),
                    Forms\Components\Toggle::make('show_on_marketing_site')->default(true),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ])->columns(2),
            Section::make('Features & Limits')
                ->schema([
                    Forms\Components\Repeater::make('features')
                        ->simple(
                            Forms\Components\TextInput::make('feature')->required()
                        )
                        ->columnSpanFull(),
                    Forms\Components\KeyValue::make('limits')->columnSpanFull(),
                ]),
            Section::make('Stripe Billing Notes')
                ->schema([
                    Forms\Components\Placeholder::make('stripe_sync_reminder')
                        ->label('Before you sync')
                        ->content('Syncing updates the Stripe catalog for future purchases. It does not automatically move existing subscribers onto a new price.'),
                    Forms\Components\Placeholder::make('website_capacity_reminder')
                        ->label('Website limits')
                        ->content('Included websites are enforced by FirePhage in the app. Stripe stores the metadata, but FirePhage decides whether a subscription still has room for more sites.'),
                    Forms\Components\Placeholder::make('price_entry_reminder')
                        ->label('Price entry format')
                        ->content('Plan prices in this form are entered in dollars. FirePhage converts them to cents when saving to the database and syncing to Stripe.'),
                ])->columns(1),
            Section::make('Stripe Sync')
                ->schema([
                    Forms\Components\TextInput::make('stripe_product_id')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('stripe_monthly_price_id')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('stripe_yearly_price_id')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('stripe_request_meter_id')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('stripe_request_overage_price_id')->disabled()->dehydrated(false),
                    Forms\Components\DateTimePicker::make('stripe_synced_at')->disabled()->dehydrated(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('badge')->toggleable(),
            Tables\Columns\TextColumn::make('monthly_price_cents')->money('USD', divideBy: 100),
            Tables\Columns\TextColumn::make('included_websites')->numeric()->label('Sites'),
            Tables\Columns\TextColumn::make('included_requests_per_month')->numeric()->label('Included / mo'),
            Tables\Columns\TextColumn::make('overage_price_cents')->money('USD', divideBy: 100)->label('Overage / block'),
            Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured'),
            Tables\Columns\IconColumn::make('show_on_marketing_site')->boolean()->label('Marketing'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('stripe_synced_at')->since()->label('Stripe Sync'),
        ])->actions([
            Actions\Action::make('syncToStripe')
                ->label('Sync to Stripe')
                ->icon('heroicon-o-arrow-path')
                ->hidden(fn (Plan $record): bool => (bool) $record->is_contact_only)
                ->requiresConfirmation()
                ->modalDescription('This updates the Stripe product and prices for future checkouts. Existing customer subscriptions are not automatically migrated to the new price.')
                ->action(function (Plan $record): void {
                    app(StripePlanSyncService::class)->sync($record);

                    Notification::make()
                        ->title('Plan synced to Stripe.')
                        ->body('Stripe catalog objects were updated for future purchases. Existing active subscriptions stay on their current Stripe price until migrated separately.')
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
