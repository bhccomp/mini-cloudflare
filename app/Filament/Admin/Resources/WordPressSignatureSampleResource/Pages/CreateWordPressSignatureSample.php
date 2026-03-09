<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Services\WordPress\WordPressSignatureLabService;
use Filament\Resources\Pages\CreateRecord;

class CreateWordPressSignatureSample extends CreateRecord
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(WordPressSignatureLabService::class)->prepareSampleData($data);
    }
}
