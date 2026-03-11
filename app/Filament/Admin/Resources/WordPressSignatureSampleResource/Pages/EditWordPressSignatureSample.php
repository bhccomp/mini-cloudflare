<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Models\WordPressSignatureSample;
use App\Services\WordPress\OpenAiSignatureSuggestionService;
use App\Services\WordPress\WordPressSignatureLabService;
use App\Services\WordPress\WordPressSignatureSampleStorageService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EditWordPressSignatureSample extends EditRecord
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('suggestDetails')
                ->label('Suggest Details')
                ->icon('heroicon-o-cpu-chip')
                ->action(function (): void {
                    try {
                        $details = app(OpenAiSignatureSuggestionService::class)->suggestSampleDetails($this->record);
                        $this->record->update($details);
                        $this->refreshFormData(['name', 'family', 'sample_type', 'notes']);

                        Notification::make()
                            ->title('Sample details updated')
                            ->body('AI suggested the sample name, family, type, and notes.')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Unable to suggest details')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('suggestSignature')
                ->label('Suggest Signature')
                ->icon('heroicon-o-sparkles')
                ->action(function (): void {
                    try {
                        $result = app(OpenAiSignatureSuggestionService::class)->suggestForSample($this->record);
                        $signature = $result['signature'];

                        Notification::make()
                            ->title('Draft signature created')
                            ->body(sprintf(
                                'Created draft "%s" with %s false-positive risk.',
                                $signature->name,
                                ucfirst((string) ($result['risk'] ?? 'unknown'))
                            ))
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Unable to generate suggestion')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $storage = app(WordPressSignatureSampleStorageService::class);
        $data = $storage->normalizeSamplePath($data);
        $prepared = app(WordPressSignatureLabService::class)->prepareSampleData($data);

        $duplicate = $storage->duplicateSampleForSha256($prepared['sha256'] ?? null, (int) $this->record->id);

        if ($duplicate instanceof WordPressSignatureSample) {
            $filePath = (string) ($prepared['file_path'] ?? '');
            $currentPath = trim((string) ($this->record->file_path ?? ''));

            if ($filePath !== '' && $filePath !== $currentPath && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            throw ValidationException::withMessages([
                'file_path' => sprintf('This exact file content already exists as sample "%s" (ID %d).', $duplicate->name, $duplicate->id),
            ]);
        }

        return $prepared;
    }
}
