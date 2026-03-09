<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWordPressSignatureSamples extends ListRecords
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
