<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\SiteResource;
use App\Jobs\ApplySiteControlSettingJob;
use App\Jobs\InvalidateCloudFrontCacheJob;
use App\Jobs\RequestAcmCertificateJob;
use App\Jobs\ToggleUnderAttackModeJob;
use App\Models\AuditLog;
use App\Models\Site;
use App\Services\SiteContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class BaseProtectionPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Protection';

    public ?Site $site = null;

    public ?int $selectedSiteId = null;

    public bool $hasAnySites = false;

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function mount(Request $request, SiteContext $siteContext): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->selectedSiteId = $siteContext->getSelectedSiteId($user, $request);
        $this->site = $siteContext->getSelectedSite($user, $request);
        $this->hasAnySites = Site::query()
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSite')
                ->label('Add Site')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(SiteResource::getUrl('create')),
            Action::make('goToSites')
                ->label('Sites')
                ->color('gray')
                ->url(SiteResource::getUrl('index')),
        ];
    }

    public function emptyStateHeading(): string
    {
        if (! $this->hasAnySites) {
            return 'No sites connected to this account yet';
        }

        return 'Select a site to view settings';
    }

    public function emptyStateDescription(): string
    {
        if (! $this->hasAnySites) {
            return 'Create your first protected site to enable SSL, CDN, cache, firewall, and origin controls.';
        }

        return 'Choose a site from the topbar switcher to load section controls.';
    }

    public function requestSsl(): void
    {
        if (! $this->site) {
            return;
        }

        RequestAcmCertificateJob::dispatch($this->site->id, auth()->id());
        $this->notify('SSL request queued');
    }

    public function purgeCache(): void
    {
        if (! $this->site) {
            return;
        }

        InvalidateCloudFrontCacheJob::dispatch($this->site->id, ['/*'], auth()->id());
        $this->notify('Cache purge queued');
    }

    public function toggleUnderAttack(): void
    {
        if (! $this->site) {
            return;
        }

        ToggleUnderAttackModeJob::dispatch($this->site->id, ! $this->site->under_attack, auth()->id());
        $this->notify('Firewall mode update queued');
    }

    public function toggleHttpsEnforcement(): void
    {
        if (! $this->site) {
            return;
        }

        $current = (bool) data_get($this->site->required_dns_records, 'control_panel.https_enforced', true);
        ApplySiteControlSettingJob::dispatch($this->site->id, 'https_enforced', ! $current, auth()->id());
        $this->notify('HTTPS enforcement update queued');
    }

    public function toggleCacheMode(): void
    {
        if (! $this->site) {
            return;
        }

        $mode = $this->cacheMode() === 'aggressive' ? 'standard' : 'aggressive';
        ApplySiteControlSettingJob::dispatch($this->site->id, 'cache_mode', $mode, auth()->id());
        $this->notify('Cache mode update queued');
    }

    public function toggleOriginProtection(): void
    {
        if (! $this->site) {
            return;
        }

        $current = (bool) data_get($this->site->required_dns_records, 'control_panel.origin_lockdown', false);
        ApplySiteControlSettingJob::dispatch($this->site->id, 'origin_lockdown', ! $current, auth()->id());
        $this->notify('Origin protection update queued');
    }

    public function certificateStatus(): string
    {
        if (! $this->site?->acm_certificate_arn) {
            return 'Not requested';
        }

        return $this->site->status === 'active' ? 'Issued' : 'Pending validation';
    }

    public function distributionHealth(): string
    {
        if (! $this->site?->cloudfront_distribution_id) {
            return 'Not deployed';
        }

        return $this->site->status === 'active' ? 'Healthy' : 'Provisioning';
    }

    public function cacheMode(): string
    {
        return (string) data_get($this->site?->required_dns_records, 'control_panel.cache_mode', 'standard');
    }

    public function metricBlockedRequests(): string
    {
        return (string) data_get($this->site?->required_dns_records, 'metrics.blocked_requests_24h', 'Coming soon');
    }

    public function metricCacheHitRatio(): string
    {
        return (string) data_get($this->site?->required_dns_records, 'metrics.cache_hit_ratio', 'Coming soon');
    }

    public function lastAction(string $startsWith): string
    {
        if (! $this->site) {
            return 'No recent actions';
        }

        $log = AuditLog::query()
            ->where('site_id', $this->site->id)
            ->where('action', 'like', $startsWith.'%')
            ->latest('id')
            ->first();

        if (! $log) {
            return 'No recent actions';
        }

        return Str::of($log->action)->replace('.', ' ')->title().' · '.$log->status;
    }

    public function badgeColor(?string $status = null): string
    {
        $status ??= $this->site?->status;

        return match ($status) {
            'active' => 'success',
            'pending_dns', 'provisioning' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    protected function notify(string $message): void
    {
        Notification::make()->title($message)->success()->send();

        $this->refreshSite();
    }

    protected function refreshSite(): void
    {
        if (! $this->site) {
            return;
        }

        $this->site = Site::query()->find($this->site->id);
    }
}
