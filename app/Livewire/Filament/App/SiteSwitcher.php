<?php

namespace App\Livewire\Filament\App;

use App\Models\Site;
use App\Services\SiteContext;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class SiteSwitcher extends Component
{
    public const RETURN_URL_SESSION_KEY = 'site_switcher_return_url';

    public ?int $selectedSiteId = null;

    public string $search = '';

    public string $returnUrl = '';

    public function mount(SiteContext $siteContext): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->selectedSiteId = $siteContext->getSelectedSiteId($user, request());
        $this->returnUrl = $this->resolveReturnUrl();
        session([self::RETURN_URL_SESSION_KEY => $this->returnUrl]);
    }

    public function sites(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return Site::query()
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('apex_domain', 'like', '%'.$this->search.'%')
                        ->orWhere('display_name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('apex_domain')
            ->limit(75)
            ->get(['id', 'display_name', 'apex_domain', 'status']);
    }

    public function selectSite(string $siteId, SiteContext $siteContext): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->selectedSiteId = $siteContext->setSelectedSiteId($user, $siteId === 'all' ? null : (int) $siteId);
        $target = (string) session(self::RETURN_URL_SESSION_KEY, $this->returnUrl);
        if ($target === '' || str_contains($target, '/livewire-')) {
            $target = \App\Filament\App\Pages\Dashboard::getUrl();
        }

        $query = $this->selectedSiteId ? '?site_id='.$this->selectedSiteId : '';

        $this->redirect($target.$query, navigate: true);
    }

    protected function resolveReturnUrl(): string
    {
        $current = request()->fullUrl();

        if (! str_contains($current, '/livewire-')) {
            return $current;
        }

        $sessionUrl = (string) session(self::RETURN_URL_SESSION_KEY, '');
        if ($sessionUrl !== '' && ! str_contains($sessionUrl, '/livewire-')) {
            return $sessionUrl;
        }

        $referer = (string) request()->headers->get('referer', '');

        return str_contains($referer, '/livewire-')
            ? \App\Filament\App\Pages\Dashboard::getUrl()
            : ($referer !== '' ? $referer : \App\Filament\App\Pages\Dashboard::getUrl());
    }

    public function addSiteUrl(): string
    {
        return \App\Filament\App\Resources\SiteResource::getUrl('create');
    }

    #[On('sites-refreshed')]
    public function refreshSites(SiteContext $siteContext): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        if (! $this->selectedSiteId) {
            return;
        }

        $exists = Site::query()
            ->where('id', $this->selectedSiteId)
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->exists();

        if ($exists) {
            return;
        }

        $this->selectedSiteId = $siteContext->setSelectedSiteId($user, null);
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

        return $site->apex_domain;
    }

    public function shortStatusLabel(string $status): string
    {
        return match ($status) {
            Site::STATUS_ACTIVE => 'Active',
            Site::STATUS_PENDING_DNS_VALIDATION => 'Validating',
            Site::STATUS_DEPLOYING => 'Deploying',
            Site::STATUS_READY_FOR_CUTOVER => 'Cutover',
            Site::STATUS_FAILED => 'Failed',
            default => 'Draft',
        };
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            Site::STATUS_ACTIVE => 'success',
            Site::STATUS_PENDING_DNS_VALIDATION, Site::STATUS_DEPLOYING, Site::STATUS_READY_FOR_CUTOVER => 'warning',
            Site::STATUS_FAILED => 'danger',
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
