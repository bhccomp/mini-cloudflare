<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
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
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('billing_email')->email()->maxLength(255),
            Forms\Components\KeyValue::make('settings')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('billing_email')->searchable(),
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
