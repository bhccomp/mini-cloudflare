<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SiteResource\Pages;
use App\Models\Organization;
use App\Models\Site;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('organization_id')
                ->required()
                ->options(Organization::query()->pluck('name', 'id')),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('apex_domain')->required(),
            Forms\Components\Select::make('environment')->options(['prod' => 'Production', 'stage' => 'Staging'])->default('prod')->required(),
            Forms\Components\Select::make('status')->options([
                'draft' => 'Draft',
                'active' => 'Active',
                'paused' => 'Paused',
            ])->default('draft')->required(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')->label('Organization')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('apex_domain')->searchable(),
                Tables\Columns\TextColumn::make('provisioning_status')->badge(),
                Tables\Columns\IconColumn::make('under_attack_mode_enabled')->boolean()->label('Under Attack'),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }
}
