<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AlertChannelResource\Pages;
use App\Models\AlertChannel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class AlertChannelResource extends Resource
{
    protected static ?string $model = AlertChannel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Alerts';

    protected static ?string $navigationLabel = 'Alert Channels';

    protected static ?string $title = 'Alert Channels';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('organization_id', auth()->user()?->organizations()->select('organizations.id') ?? []);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAlertChannels::route('/'),
        ];
    }
}
