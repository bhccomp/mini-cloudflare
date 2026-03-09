<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Services\WordPress\WordPressSignatureLabService;
use Filament\Resources\Pages\EditRecord;

class EditWordPressSignatureSample extends EditRecord
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(WordPressSignatureLabService::class)->prepareSampleData($data);
    }
}
