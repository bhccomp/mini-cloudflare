<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AlertRuleResource\Pages;
use App\Models\AlertRule;
use App\Models\Site;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlertRuleResource extends Resource
{
    protected static ?string $model = AlertRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|\UnitEnum|null $navigationGroup = 'Alerts';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('site', fn (Builder $query) => $query->whereIn('organization_id', auth()->user()?->organizations()->select('organizations.id') ?? []));
    }

    public static function form(Schema $schema): Schema
    {
        $siteOptions = Site::query()
            ->whereIn('organization_id', auth()->user()?->organizations()->select('organizations.id') ?? [])
            ->pluck('apex_domain', 'id');

        return $schema->schema([
            Forms\Components\Select::make('site_id')->options($siteOptions)->required(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('event_type')->required()->helperText('Examples: waf.blocks_per_minute, login.failures'),
            Forms\Components\TextInput::make('threshold')->numeric()->required(),
            Forms\Components\TextInput::make('window_minutes')->numeric()->default(5)->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('site.apex_domain')->label('Site'),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('event_type')->searchable(),
            Tables\Columns\TextColumn::make('threshold'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlertRules::route('/'),
            'create' => Pages\CreateAlertRule::route('/create'),
            'edit' => Pages\EditAlertRule::route('/{record}/edit'),
        ];
    }
}
