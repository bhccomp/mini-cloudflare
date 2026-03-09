<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Models\WordPressSignatureSample;
use App\Services\WordPress\OpenAiSignatureSuggestionService;
use App\Services\WordPress\WordPressSignatureLabService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWordPressSignatureSample extends CreateRecord
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prepared = app(WordPressSignatureLabService::class)->prepareSampleData($data);

        try {
            $temporarySample = new WordPressSignatureSample($prepared);
            $aiDetails = app(OpenAiSignatureSuggestionService::class)->suggestSampleDetails($temporarySample);
            $prepared = array_merge($prepared, array_filter($aiDetails, static fn ($value): bool => is_string($value) && trim($value) !== ''));
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('AI details not generated')
                ->body('The sample was still created. You can use Suggest Details after saving if needed.')
                ->warning()
                ->send();
        }

        return $prepared;
    }
}
