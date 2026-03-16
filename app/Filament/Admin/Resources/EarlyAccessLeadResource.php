<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EarlyAccessLeadResource\Pages;
use App\Models\EarlyAccessLead;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EarlyAccessLeadResource extends Resource
{
    protected static ?string $model = EarlyAccessLead::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Early access lead';

    protected static ?string $pluralModelLabel = 'Early access leads';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->required()->email()->maxLength(255),
            Forms\Components\TextInput::make('company_name')->maxLength(255),
            Forms\Components\TextInput::make('website_url')->url()->maxLength(255),
            Forms\Components\TextInput::make('monthly_requests_band')->maxLength(255),
            Forms\Components\TextInput::make('websites_managed')->maxLength(255),
            Forms\Components\Toggle::make('wants_launch_discount')->inline(false),
            Forms\Components\Textarea::make('notes')->rows(5)->columnSpanFull(),
            Forms\Components\TextInput::make('ip_address')->disabled()->dehydrated(false),
            Forms\Components\Textarea::make('user_agent')->disabled()->dehydrated(false)->columnSpanFull(),
            Forms\Components\DateTimePicker::make('signed_up_at'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('signed_up_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('company_name')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('website_url')->toggleable(),
                Tables\Columns\TextColumn::make('monthly_requests_band')->label('Traffic')->toggleable(),
                Tables\Columns\TextColumn::make('websites_managed')->label('Sites')->toggleable(),
                Tables\Columns\IconColumn::make('wants_launch_discount')->boolean()->label('Discount'),
                Tables\Columns\TextColumn::make('signed_up_at')->since()->label('Signed up'),
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
            'index' => Pages\ListEarlyAccessLeads::route('/'),
            'edit' => Pages\EditEarlyAccessLead::route('/{record}/edit'),
        ];
    }
}
