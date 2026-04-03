<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Billing\AdminBillingOverviewService;
use App\Services\Billing\OrganizationEntitlementService;
use App\Services\Billing\OrganizationBillingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Tenant Management';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Organization')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('billing_email')->email()->maxLength(255),
                ])->columns(2),
            Section::make('Billing Access')
                ->schema([
                    Forms\Components\Select::make('settings.billing_mode')
                        ->label('Billing mode')
                        ->options([
                            OrganizationEntitlementService::MODE_STRIPE => 'Stripe subscription',
                            OrganizationEntitlementService::MODE_MANUAL_TRIAL => 'Manual trial',
                            OrganizationEntitlementService::MODE_COMPED => 'Comped / free access',
                        ])
                        ->default(OrganizationEntitlementService::MODE_STRIPE)
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('settings.assigned_plan_id')
                        ->label('Assigned plan')
                        ->options(fn () => Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Used for manual trials and comped accounts. Stripe mode still uses live subscriptions when present.'),
                    Forms\Components\DateTimePicker::make('settings.trial_ends_at')
                        ->label('Manual trial ends at')
                        ->seconds(false)
                        ->visible(fn (Get $get): bool => $get('settings.billing_mode') === OrganizationEntitlementService::MODE_MANUAL_TRIAL)
                        ->helperText('Only used when Billing mode is set to Manual trial.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('billing_email')->searchable(),
                Tables\Columns\TextColumn::make('billing_status')
                    ->label('Billing')
                    ->state(fn (Organization $record): string => app(AdminBillingOverviewService::class)->summaryForOrganization($record)['status_label'])
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active', 'Comped' => 'success',
                        'Trialing', 'Manual Trial', 'Syncing' => 'info',
                        'Past Due' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('effective_plan')
                    ->label('Assigned plan')
                    ->state(fn (Organization $record): ?string => app(AdminBillingOverviewService::class)->summaryForOrganization($record)['plan_label'])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sites_count')->counts('sites')->label('Sites'),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Users'),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Actions\Action::make('generate_payment_link')
                    ->label('Generate Stripe link')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (): bool => app(OrganizationBillingService::class)->hasStripeConfigured())
                    ->url(fn (Organization $record): string => static::getUrl('generate-payment-link', ['record' => $record])),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
            'generate-payment-link' => Pages\GeneratePaymentLink::route('/{record}/generate-payment-link'),
        ];
    }
}
