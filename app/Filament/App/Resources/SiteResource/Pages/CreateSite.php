<?php

namespace App\Filament\App\Resources\SiteResource\Pages;

use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Resources\SiteResource;
use App\Services\SiteContext;
use Filament\Actions\Action;
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

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create protection layer');
    }

    protected function getRedirectUrl(): string
    {
        return Dashboard::getUrl(['site_id' => $this->record->id]);
    }

    protected function afterCreate(): void
    {
        app(SiteContext::class)->setSelectedSiteId(auth()->user(), $this->record->id);

        Notification::make()
            ->title('Site created')
            ->body('Your site is selected. Continue setup from the Overview section.')
            ->success()
            ->send();
    }
}
