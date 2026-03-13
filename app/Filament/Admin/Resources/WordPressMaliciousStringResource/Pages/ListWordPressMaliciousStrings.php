<?php

namespace App\Filament\Admin\Resources\WordPressMaliciousStringResource\Pages;

use App\Filament\Admin\Resources\WordPressMaliciousStringResource;
use App\Services\WordPress\WordPressMaliciousStringService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWordPressMaliciousStrings extends ListRecords
{
    protected static string $resource = WordPressMaliciousStringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('runTestAll')
                ->label('Run Test All')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(WordPressMaliciousStringService::class)->testAllActiveStrings();

                    Notification::make()
                        ->title('All malicious string tests completed')
                        ->body(sprintf(
                            'Tested %d active string(s). %d string(s) matched at least one malware sample. Total malware hits across results: %d.',
                            (int) ($result['tested'] ?? 0),
                            (int) ($result['matched'] ?? 0),
                            (int) ($result['total_malware_hits'] ?? 0),
                        ))
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }
}
