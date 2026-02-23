<?php

namespace App\Filament\Admin\Resources\SiteResource\Pages;

use App\Filament\Admin\Resources\SiteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
