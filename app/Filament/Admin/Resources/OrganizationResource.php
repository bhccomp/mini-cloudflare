<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Billing\OrganizationEntitlementService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
                        ->visible(fn (Forms\Get $get): bool => $get('settings.billing_mode') === OrganizationEntitlementService::MODE_MANUAL_TRIAL)
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
                Tables\Columns\TextColumn::make('settings.billing_mode')->label('Billing')->badge(),
                Tables\Columns\TextColumn::make('settings.assigned_plan_id')
                    ->label('Assigned plan')
                    ->formatStateUsing(fn ($state) => $state ? Plan::query()->find($state)?->name : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sites_count')->counts('sites')->label('Sites'),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Users'),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
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
        ];
    }
}
