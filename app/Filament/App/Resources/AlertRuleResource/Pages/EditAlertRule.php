<?php

namespace App\Filament\App\Resources\AlertRuleResource\Pages;

use App\Filament\App\Resources\AlertRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAlertRule extends EditRecord
{
    protected static string $resource = AlertRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
