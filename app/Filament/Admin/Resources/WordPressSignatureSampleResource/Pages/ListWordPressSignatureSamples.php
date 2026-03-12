<?php

namespace App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;

use App\Filament\Admin\Resources\WordPressMalwareSignatureResource;
use App\Filament\Admin\Resources\WordPressSignatureSampleResource;
use App\Services\WordPress\WordPressSignatureSampleStorageService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Storage;

class ListWordPressSignatureSamples extends ListRecords
{
    protected static string $resource = WordPressSignatureSampleResource::class;

    public function getTabs(): array
    {
        return [
            'created_signatures' => Tab::make('Created Signatures'),
            'signature_samples' => Tab::make('Signature Samples'),
        ];
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        if ($this->activeTab === 'created_signatures') {
            $this->redirect(WordPressMalwareSignatureResource::getUrl('index', ['tab' => 'created_signatures']));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('scanSyncDrift')
                ->label('Scan Sync Drift')
                ->icon('heroicon-o-magnifying-glass')
                ->action(function (): void {
                    $result = app(WordPressSignatureSampleStorageService::class)->scanAndSync(false);

                    Notification::make()
                        ->title('Sample library scan completed')
                        ->body(sprintf(
                            'Tracked files: %d. Missing files: %d. Untracked files: %d.',
                            (int) ($result['tracked_files'] ?? 0),
                            (int) ($result['missing_files'] ?? 0),
                            (int) ($result['untracked_files'] ?? 0),
                        ))
                        ->success()
                        ->send();
                }),
            Actions\Action::make('syncSampleLibrary')
                ->label('Sync Sample Library')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(WordPressSignatureSampleStorageService::class)->scanAndSync(true);

                    Notification::make()
                        ->title('Sample library synced')
                        ->body(sprintf(
                            'Created %d missing files and moved %d legacy files. Untracked files remaining: %d.',
                            (int) ($result['missing_files_created'] ?? 0),
                            (int) ($result['legacy_files_moved'] ?? 0),
                            (int) ($result['untracked_files'] ?? 0),
                        ))
                        ->success()
                        ->send();
                }),
            Actions\Action::make('importZip')
                ->label('Import ZIP')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->form([
                    Forms\Components\FileUpload::make('archive')
                        ->label('ZIP archive')
                        ->disk('local')
                        ->directory('wordpress-signature-samples/tmp')
                        ->acceptedFileTypes([
                            'application/zip',
                            'application/x-zip-compressed',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $archivePath = (string) ($data['archive'] ?? '');
                    $result = app(WordPressSignatureSampleStorageService::class)->importArchive($archivePath);

                    if ($archivePath !== '' && Storage::disk('local')->exists($archivePath)) {
                        Storage::disk('local')->delete($archivePath);
                    }

                    Notification::make()
                        ->title('ZIP import completed')
                        ->body(sprintf(
                            'Imported %d sample files and skipped %d.',
                            (int) ($result['imported'] ?? 0),
                            (int) ($result['skipped'] ?? 0),
                        ))
                        ->success()
                        ->send();

                    if (($result['errors'] ?? []) !== []) {
                        Notification::make()
                            ->title('ZIP import warnings')
                            ->body(implode("\n", $result['errors']))
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'signature_samples';
    }
}
