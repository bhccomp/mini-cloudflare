<?php

namespace App\Filament\App\Resources\AlertChannelResource\Pages;

use App\Filament\App\Resources\AlertChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAlertChannel extends EditRecord
{
    protected static string $resource = AlertChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
