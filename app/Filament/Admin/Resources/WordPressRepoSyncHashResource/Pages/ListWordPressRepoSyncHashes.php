<?php

namespace App\Filament\Admin\Resources\WordPressRepoSyncHashResource\Pages;

use App\Filament\Admin\Resources\WordPressMalwareSignatureResource;
use App\Filament\Admin\Resources\WordPressRepoSyncHashResource;
use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Services\WordPress\WordPressRepoSyncHashService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListWordPressRepoSyncHashes extends ListRecords
{
    protected static string $resource = WordPressRepoSyncHashResource::class;

    public function getTabs(): array
    {
        return [
            'created_signatures' => Tab::make('Created Signatures'),
            'signature_samples' => Tab::make('Signature Samples'),
            'repo_sync_hashes' => Tab::make('Romainmarcoux Repo Sync Hashes'),
        ];
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        if ($this->activeTab === 'created_signatures') {
            $this->redirect(WordPressMalwareSignatureResource::getUrl('index', ['tab' => 'created_signatures']));
        }

        if ($this->activeTab === 'signature_samples') {
            $this->redirect(WordPressSignatureSampleResource::getUrl('index', ['tab' => 'signature_samples']));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncGitHubFeed')
                ->label('Sync from GitHub')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(WordPressRepoSyncHashService::class)->syncFromGitHub();

                    Notification::make()
                        ->title('Repo sync hashes refreshed')
                        ->body(sprintf(
                            'Imported %d new hashes, updated %d existing hashes, skipped %d invalid lines.',
                            (int) ($result['imported'] ?? 0),
                            (int) ($result['updated'] ?? 0),
                            (int) ($result['skipped'] ?? 0),
                        ))
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'repo_sync_hashes';
    }
}
