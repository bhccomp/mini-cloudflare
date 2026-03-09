<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Services\WordPress\OpenAiSignatureSuggestionService;
use App\Services\WordPress\WordPressSignatureLabService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWordPressSignatureSample extends EditRecord
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        return app(WordPressSignatureLabService::class)->prepareSampleData($data);
    }
}
