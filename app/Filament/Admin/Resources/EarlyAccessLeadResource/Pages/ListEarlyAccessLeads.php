<?php

namespace App\Filament\Admin\Resources\EarlyAccessLeadResource\Pages;

use App\Filament\Admin\Resources\EarlyAccessLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEarlyAccessLeads extends ListRecords
{
    protected static string $resource = EarlyAccessLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
