<?php

namespace App\Filament\App\Resources\SiteResource\Pages;

use App\Filament\App\Resources\SiteResource;
use App\Models\Site;
use Filament\Resources\Pages\EditRecord;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    public function mount(int|string $record): void
    {
        $selectedSiteId = (int) session('selected_site_id');

        if ($selectedSiteId < 1) {
            $this->redirect(SiteResource::getUrl('index'), navigate: true);

            return;
        }

        $site = Site::query()
            ->whereKey($selectedSiteId)
            ->whereIn('organization_id', auth()->user()?->organizations()->select('organizations.id') ?? [])
            ->first();

        if (! $site) {
            session()->forget('selected_site_id');
            $this->redirect(SiteResource::getUrl('index'), navigate: true);

            return;
        }

        if ((int) $record !== $site->id) {
            $this->redirect(SiteResource::getUrl('edit', ['record' => $site]), navigate: true);

            return;
        }

        parent::mount($site->id);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
