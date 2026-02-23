<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AlertEventResource\Pages;
use App\Models\AlertEvent;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlertEventResource extends Resource
{
    protected static ?string $model = AlertEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Alerts';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('site', fn (Builder $query) => $query->whereIn('organization_id', auth()->user()?->organizations()->select('organizations.id') ?? []));
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->dateTime(),
                Tables\Columns\TextColumn::make('site.apex_domain')->label('Site'),
                Tables\Columns\TextColumn::make('severity')->badge(),
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('payload')->label('Details')->formatStateUsing(fn () => 'Coming soon'),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlertEvents::route('/'),
        ];
    }
}
