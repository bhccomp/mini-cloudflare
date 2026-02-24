<?php

namespace App\Livewire\Filament\App;

use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Support\Collection;
use Livewire\Component;

class SiteSwitcher extends Component
{
    public ?int $selectedSiteId = null;

    public function mount(): void
    {
        $selected = (int) session('selected_site_id');

        if ($selected > 0 && $this->sites()->contains(fn (Site $site): bool => $site->id === $selected)) {
            $this->selectedSiteId = $selected;

            return;
        }

        $this->selectedSiteId = null;
        session()->forget('selected_site_id');
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

    public function selectSite(int $siteId): void
    {
        $site = $this->sites()->firstWhere('id', $siteId);

        if (! $site) {
            return;
        }

        session(['selected_site_id' => $site->id]);
        $this->selectedSiteId = $site->id;

        $this->redirect(Dashboard::getUrl(), navigate: true);
    }

    public function clearSelectedSite(): void
    {
        session()->forget('selected_site_id');
        $this->selectedSiteId = null;

        $this->redirect(SiteResource::getUrl('index'), navigate: true);
    }

    public function addSiteUrl(): string
    {
        return SiteResource::getUrl('create');
    }

    public function getSelectedSiteProperty(): ?Site
    {
        if (! $this->selectedSiteId) {
            return null;
        }

        return $this->sites()->firstWhere('id', $this->selectedSiteId);
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
