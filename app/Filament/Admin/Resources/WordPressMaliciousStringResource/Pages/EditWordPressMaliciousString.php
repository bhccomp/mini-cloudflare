<?php

namespace App\Filament\Admin\Resources\WordPressMaliciousStringResource\Pages;

use App\Filament\Admin\Resources\WordPressMaliciousStringResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWordPressMaliciousString extends EditRecord
{
    protected static string $resource = WordPressMaliciousStringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
