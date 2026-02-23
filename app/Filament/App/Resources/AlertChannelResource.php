<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AlertChannelResource\Pages;
use App\Models\AlertChannel;
use App\Models\Site;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlertChannelResource extends Resource
{
    protected static ?string $model = AlertChannel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Alerts';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('organization_id', auth()->user()?->organizations()->select('organizations.id') ?? []);
    }

    public static function form(Schema $schema): Schema
    {
        $orgIds = auth()->user()?->organizations()->pluck('organizations.id') ?? collect();
        $siteOptions = Site::query()->whereIn('organization_id', $orgIds)->pluck('apex_domain', 'id');

        return $schema->schema([
            Forms\Components\Hidden::make('organization_id')
                ->default(fn () => auth()->user()?->current_organization_id ?: auth()->user()?->organizations()->value('organizations.id')),
            Forms\Components\Select::make('site_id')->options($siteOptions),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Select::make('type')->options([
                'email' => 'Email',
                'webhook' => 'Webhook',
                'slack' => 'Slack',
            ])->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\KeyValue::make('config')->helperText('Examples: {"address":"ops@example.com"} or {"url":"https://..."}')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('site.apex_domain')->label('Site')->toggleable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlertChannels::route('/'),
            'create' => Pages\CreateAlertChannel::route('/create'),
            'edit' => Pages\EditAlertChannel::route('/{record}/edit'),
        ];
    }
}
