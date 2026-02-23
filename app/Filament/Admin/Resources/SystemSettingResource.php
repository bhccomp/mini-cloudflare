<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SystemSettingResource\Pages;
use App\Models\SystemSetting;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('key')->required()->unique(ignoreRecord: true),
            Forms\Components\Toggle::make('is_encrypted'),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\KeyValue::make('value')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->searchable(),
                Tables\Columns\IconColumn::make('is_encrypted')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemSettings::route('/'),
            'create' => Pages\CreateSystemSetting::route('/create'),
            'edit' => Pages\EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
