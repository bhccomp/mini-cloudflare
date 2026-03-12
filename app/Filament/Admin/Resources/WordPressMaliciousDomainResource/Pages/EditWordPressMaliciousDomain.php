<?php

namespace App\Filament\Admin\Resources\WordPressMaliciousDomainResource\Pages;

use App\Filament\Admin\Resources\WordPressMaliciousDomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWordPressMaliciousDomain extends EditRecord
{
    protected static string $resource = WordPressMaliciousDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
