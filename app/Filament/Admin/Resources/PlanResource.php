<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Models\Plan;
use App\Services\Billing\StripePlanSyncService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Section::make('Plan Identity')
                ->schema([
                    Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('headline')->maxLength(255),
                    Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('monthly_price_cents')
                        ->numeric()
                        ->required()
                        ->prefix('$ cents'),
                    Forms\Components\TextInput::make('yearly_price_cents')
                        ->numeric()
                        ->required()
                        ->prefix('$ cents'),
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
                    Forms\Components\Toggle::make('is_contact_only')->default(false),
                    Forms\Components\Toggle::make('show_on_marketing_site')->default(true),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ])->columns(2),
            Forms\Components\Section::make('Features & Limits')
                ->schema([
                    Forms\Components\Repeater::make('features')
                        ->simple(
                            Forms\Components\TextInput::make('feature')->required()
                        )
                        ->columnSpanFull(),
                    Forms\Components\KeyValue::make('limits')->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Stripe Sync')
                ->schema([
                    Forms\Components\TextInput::make('stripe_product_id')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('stripe_monthly_price_id')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('stripe_yearly_price_id')->disabled()->dehydrated(false),
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
            Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured'),
            Tables\Columns\IconColumn::make('show_on_marketing_site')->boolean()->label('Marketing'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('stripe_synced_at')->since()->label('Stripe Sync'),
        ])->actions([
            Actions\Action::make('syncToStripe')
                ->label('Sync to Stripe')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (Plan $record): void {
                    app(StripePlanSyncService::class)->sync($record);

                    Notification::make()
                        ->title('Plan synced to Stripe.')
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
