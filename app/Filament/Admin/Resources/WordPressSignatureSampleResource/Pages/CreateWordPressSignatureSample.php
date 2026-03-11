<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Models\WordPressSignatureSample;
use App\Services\WordPress\OpenAiSignatureSuggestionService;
use App\Services\WordPress\WordPressSignatureLabService;
use App\Services\WordPress\WordPressSignatureSampleStorageService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class CreateWordPressSignatureSample extends CreateRecord
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $storage = app(WordPressSignatureSampleStorageService::class);
        $data = $storage->normalizeSamplePath($data);
        $prepared = app(WordPressSignatureLabService::class)->prepareSampleData($data);

        $duplicate = $storage->duplicateSampleForSha256($prepared['sha256'] ?? null);

        if ($duplicate instanceof WordPressSignatureSample) {
            $filePath = (string) ($prepared['file_path'] ?? '');

            if ($filePath !== '' && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            throw ValidationException::withMessages([
                'file_path' => sprintf('This exact file content already exists as sample "%s" (ID %d).', $duplicate->name, $duplicate->id),
            ]);
        }

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
