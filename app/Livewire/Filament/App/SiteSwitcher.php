<?php

namespace App\Livewire\Filament\App;

use App\Models\Site;
use App\Services\SiteContext;
use Illuminate\Support\Collection;
use Livewire\Component;

class SiteSwitcher extends Component
{
    public ?int $selectedSiteId = null;

    public function mount(SiteContext $siteContext): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->selectedSiteId = $siteContext->getSelectedSiteId($user, request());
    }

    public function sites(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return Site::query()
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'apex_domain', 'status']);
    }

    public function selectSite(string $siteId, SiteContext $siteContext): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->selectedSiteId = $siteContext->setSelectedSiteId($user, $siteId === 'all' ? null : (int) $siteId);

        $this->redirect(request()->fullUrlWithoutQuery(['site_id']).($this->selectedSiteId ? '?site_id='.$this->selectedSiteId : ''), navigate: true);
    }

    public function addSiteUrl(): string
    {
        return \App\Filament\App\Resources\SiteResource::getUrl('create');
    }

    public function getSelectedLabelProperty(): string
    {
        if (! $this->selectedSiteId) {
            return 'All sites';
        }

        $site = $this->sites()->firstWhere('id', $this->selectedSiteId);

        if (! $site) {
            return 'All sites';
        }

        return $site->display_name.' · '.$site->apex_domain;
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'pending_dns', 'provisioning' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function render()
    {
        return view('livewire.filament.app.site-switcher', [
            'sites' => $this->sites(),
        ]);
    }
}
