<?php

namespace App\Filament\Admin\Resources\WordPressMaliciousDomainResource\Pages;

use App\Filament\Admin\Resources\WordPressMaliciousDomainResource;
use App\Models\WordPressMaliciousDomain;
use App\Services\WordPress\WordPressMaliciousDomainService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWordPressMaliciousDomains extends ListRecords
{
    protected static string $resource = WordPressMaliciousDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('importBundledFeed')
                ->label('Import GitHub Feed')
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(WordPressMaliciousDomainService::class)->importFromFile(
                        WordPressMaliciousDomainResource::bundledFeedPath()
                    );

                    Notification::make()
                        ->title('Malicious domain feed imported')
                        ->body(sprintf(
                            'Imported %d new domains, updated %d existing domains, skipped %d invalid lines.',
                            (int) ($result['imported'] ?? 0),
                            (int) ($result['updated'] ?? 0),
                            (int) ($result['skipped'] ?? 0),
                        ))
                        ->persistent()
                        ->success()
                        ->send();
                }),
            Actions\Action::make('runTestAll')
                ->label('Run Test All')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(WordPressMaliciousDomainService::class)->testAllActiveDomains();

                    Notification::make()
                        ->title('All malicious domain tests completed')
                        ->body(sprintf(
                            'Tested %d active domain(s). %d domain(s) matched at least one malware sample. Total malware hits across results: %d.',
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
