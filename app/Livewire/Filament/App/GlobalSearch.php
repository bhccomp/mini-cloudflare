<?php

namespace App\Livewire\Filament\App;

use App\Filament\App\Pages\AnalyticsPage;
use App\Filament\App\Pages\AvailabilityMonitorPage;
use App\Filament\App\Pages\CachePage;
use App\Filament\App\Pages\CdnPage;
use App\Filament\App\Pages\FirewallAccessControlPage;
use App\Filament\App\Pages\FirewallPage;
use App\Filament\App\Pages\FirewallRateLimitingPage;
use App\Filament\App\Pages\FirewallShieldSettingsPage;
use App\Filament\App\Pages\OriginPage;
use App\Filament\App\Pages\SiteStatusHubPage;
use App\Filament\App\Pages\SslTlsPage;
use App\Filament\App\Resources\AlertChannelResource;
use App\Filament\App\Resources\AlertEventResource;
use App\Filament\App\Resources\AlertRuleResource;
use App\Filament\App\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Support\Collection;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public function results(): Collection
    {
        $query = trim($this->query);

        return $this->pageResults($query)
            ->concat($this->siteResults($query))
            ->take(18)
            ->values();
    }

    public function open(string $url): void
    {
        if ($url === '' || str_contains($url, '/livewire-')) {
            return;
        }

        $this->redirect($url, navigate: true);
    }

    protected function pageResults(string $query): Collection
    {
        $items = collect([
            ['type' => 'Page', 'label' => 'Status Hub', 'meta' => 'Onboarding and protection status', 'url' => SiteStatusHubPage::getUrl()],
            ['type' => 'Page', 'label' => 'Overview', 'meta' => 'Security overview', 'url' => FirewallPage::getUrl()],
            ['type' => 'Page', 'label' => 'WAF Access Control', 'meta' => 'Country/IP/CIDR rules', 'url' => FirewallAccessControlPage::getUrl()],
            ['type' => 'Page', 'label' => 'DDoS Settings', 'meta' => 'Shield and sensitivity', 'url' => FirewallShieldSettingsPage::getUrl()],
            ['type' => 'Page', 'label' => 'Rate Limiting', 'meta' => 'Rate control rules', 'url' => FirewallRateLimitingPage::getUrl()],
            ['type' => 'Page', 'label' => 'SSL/TLS', 'meta' => 'Certificates and HTTPS', 'url' => SslTlsPage::getUrl()],
            ['type' => 'Page', 'label' => 'CDN', 'meta' => 'Edge delivery controls', 'url' => CdnPage::getUrl()],
            ['type' => 'Page', 'label' => 'Cache', 'meta' => 'Purge and cache controls', 'url' => CachePage::getUrl()],
            ['type' => 'Page', 'label' => 'Origin', 'meta' => 'Origin security and health', 'url' => OriginPage::getUrl()],
            ['type' => 'Page', 'label' => 'Analytics', 'meta' => 'Traffic and security analytics', 'url' => AnalyticsPage::getUrl()],
            ['type' => 'Page', 'label' => 'Availability Monitor', 'meta' => 'Uptime checks and incidents', 'url' => AvailabilityMonitorPage::getUrl()],
            ['type' => 'Resource', 'label' => 'Sites', 'meta' => 'Manage protected domains', 'url' => SiteResource::getUrl('index')],
            ['type' => 'Resource', 'label' => 'Alert Rules', 'meta' => 'Alert conditions', 'url' => AlertRuleResource::getUrl('index')],
            ['type' => 'Resource', 'label' => 'Alert Channels', 'meta' => 'Email/webhook destinations', 'url' => AlertChannelResource::getUrl('index')],
            ['type' => 'Resource', 'label' => 'Alert Events', 'meta' => 'Recent alert activity', 'url' => AlertEventResource::getUrl('index')],
        ]);

        if ($query === '') {
            return $items->take(8);
        }

        return $items->filter(function (array $item) use ($query): bool {
            $haystack = strtolower($item['label'].' '.$item['meta'].' '.$item['type']);

            return str_contains($haystack, strtolower($query));
        });
    }

    protected function siteResults(string $query): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return Site::query()
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder
                        ->where('apex_domain', 'like', '%'.$query.'%')
                        ->orWhere('display_name', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('apex_domain')
            ->limit($query === '' ? 6 : 10)
            ->get(['id', 'apex_domain', 'status'])
            ->map(function (Site $site): array {
                return [
                    'type' => 'Site',
                    'label' => $site->apex_domain,
                    'meta' => 'Open status hub',
                    'url' => SiteStatusHubPage::getUrl().'?site_id='.$site->id,
                    'status' => $site->status,
                ];
            });
    }

    public function badgeColor(string $type): string
    {
        return match ($type) {
            'Site' => 'info',
            'Resource' => 'gray',
            default => 'primary',
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

    public function render()
    {
        return view('livewire.filament.app.global-search', [
            'results' => $this->results(),
        ]);
    }
}
