<?php

namespace App\Filament\App\Resources\SiteResource\Pages;

use App\Filament\App\Resources\SiteResource;
use App\Jobs\RequestAcmCertificateJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['origin_type'] = 'url';

        return $data;
    }

    protected function afterCreate(): void
    {
        RequestAcmCertificateJob::dispatch($this->record->id, auth()->id());

        Notification::make()
            ->title('SSL request queued')
            ->body('ACM certificate request started. Add DNS records once generated.')
            ->success()
            ->send();
    }
}
