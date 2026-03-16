<?php

namespace App\Filament\Admin\Resources\EarlyAccessLeadResource\Pages;

use App\Filament\Admin\Resources\EarlyAccessLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEarlyAccessLead extends EditRecord
{
    protected static string $resource = EarlyAccessLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
