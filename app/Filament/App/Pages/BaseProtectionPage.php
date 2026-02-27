<?php

namespace App\Filament\App\Pages;

use App\Jobs\ApplySiteControlSettingJob;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Jobs\InvalidateCloudFrontCacheJob;
use App\Jobs\MarkSiteReadyForCutoverJob;
use App\Jobs\ProvisionEdgeDeploymentJob;
use App\Jobs\RequestAcmCertificateJob;
use App\Jobs\ToggleUnderAttackModeJob;
use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use App\Services\SiteContext;
use App\Services\UiModeManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

abstract class BaseProtectionPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Protection';

    public ?Site $site = null;

    public ?int $selectedSiteId = null;

    public bool $hasAnySites = false;

    public bool $pollingEnabled = false;

    public string $uiMode = UiModeManager::SIMPLE;

    /** @var Collection<int, Site> */
    public Collection $availableSites;

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function mount(Request $request, SiteContext $siteContext, UiModeManager $uiMode): void
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
        $this->availableSites = Site::query()
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->orderBy('apex_domain')
            ->get(['id', 'apex_domain', 'display_name', 'status']);
        $this->pollingEnabled = $this->shouldAutoPollByStatus();
        $this->uiMode = $uiMode->current($user);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function isProMode(): bool
    {
        return $this->uiMode === UiModeManager::PRO;
    }

    public function isSimpleMode(): bool
    {
        return ! $this->isProMode();
    }

    public function showProModePrompt(): bool
    {
        return $this->isSimpleMode() && $this->site !== null;
    }

    public function switchToProMode(UiModeManager $uiMode): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->uiMode = $uiMode->setMode($user, UiModeManager::PRO);
        $this->notify('Switched to Pro mode');
        $this->dispatch('ui-mode-changed', mode: $this->uiMode);
        $this->redirect($this->safeReturnUrl(), navigate: true);
    }

    #[On('ui-mode-changed')]
    public function onUiModeChanged(string $mode = UiModeManager::SIMPLE): void
    {
        $this->uiMode = app(UiModeManager::class)->normalize($mode);
    }

    protected function safeReturnUrl(): string
    {
        $referer = (string) request()->headers->get('referer', '');

        if ($referer !== '' && str_contains($referer, '/app/') && ! str_contains($referer, '/livewire-')) {
            return $referer;
        }

        if ($this->site) {
            return static::getUrl(['site_id' => $this->site->id]);
        }

        return static::getUrl();
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

        $this->pollingEnabled = true;
        if (! $this->throttle('provision')) {
            return;
        }

        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            $this->site->update([
                'status' => Site::STATUS_DEPLOYING,
                'onboarding_status' => Site::ONBOARDING_PROVISIONING_EDGE,
                'last_error' => null,
            ]);

            Bus::chain([
                new ProvisionEdgeDeploymentJob($this->site->id, auth()->id()),
                new MarkSiteReadyForCutoverJob($this->site->id, auth()->id()),
            ])->dispatch();

            $this->notify('Edge provisioning queued');

            return;
        }

        RequestAcmCertificateJob::dispatch($this->site->id, auth()->id());
        $this->notify('Provision request queued');
    }

    public function checkDnsValidation(): void
    {
        if (! $this->site) {
            return;
        }

        $this->pollingEnabled = true;
        if (! $this->throttle('check-dns-validation')) {
            return;
        }
        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            $this->runBunnyDnsCheckNow();
            $this->notify($this->bunnyCheckMessage());

            return;
        }

        CheckAcmDnsValidationJob::dispatch($this->site->id, auth()->id());
        $this->notify('Validation check queued');
    }

    public function checkCutover(): void
    {
        if (! $this->site) {
            return;
        }

        $this->pollingEnabled = true;
        if (! $this->throttle('check-cutover')) {
            return;
        }
        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            $this->runBunnyDnsCheckNow();
            $this->notify($this->bunnyCheckMessage());

            return;
        }

        CheckAcmDnsValidationJob::dispatch($this->site->id, auth()->id());
        $this->notify('Cutover check queued');
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
        if ($this->site?->provider === Site::PROVIDER_BUNNY) {
            return match ($this->site->onboarding_status) {
                Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING => 'DNS OK, SSL pending',
                Site::ONBOARDING_LIVE => 'Active',
                default => 'Pending',
            };
        }

        if (! $this->site?->acm_certificate_arn) {
            return 'Not requested';
        }

        return $this->site->status === Site::STATUS_ACTIVE ? 'Issued' : 'Pending validation';
    }

    public function distributionHealth(): string
    {
        if (! $this->site?->cloudfront_distribution_id) {
            return 'Not deployed';
        }

        return $this->site->status === Site::STATUS_ACTIVE ? 'Healthy' : 'Provisioning';
    }

    public function cacheMode(): string
    {
        return (string) data_get($this->site?->required_dns_records, 'control_panel.cache_mode', 'standard');
    }

    public function metricBlockedRequests(): string
    {
        $value = $this->site?->analyticsMetric?->blocked_requests_24h
            ?? data_get($this->site?->required_dns_records, 'metrics.blocked_requests_24h');

        return $value !== null ? number_format((int) $value) : 'Coming soon';
    }

    public function metricCacheHitRatio(): string
    {
        $value = $this->site?->analyticsMetric?->cache_hit_ratio
            ?? data_get($this->site?->required_dns_records, 'metrics.cache_hit_ratio');

        return $value !== null ? number_format((float) $value, 2).'%' : 'Coming soon';
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
            Site::STATUS_ACTIVE => 'success',
            Site::STATUS_PENDING_DNS_VALIDATION, Site::STATUS_DEPLOYING, Site::STATUS_READY_FOR_CUTOVER => 'warning',
            Site::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    public function statusLabel(?string $status = null): string
    {
        $status ??= $this->site?->status;

        return Site::statuses()[$status] ?? str($status)->replace('_', ' ')->title()->toString();
    }

    public function currentPageBaseUrl(): string
    {
        return request()->url();
    }

    protected function throttle(string $action): bool
    {
        if (! $this->site || ! auth()->check()) {
            return false;
        }

        $key = sprintf('site-action:%s:%s:%s', auth()->id(), $this->site->id, $action);

        if (! \Illuminate\Support\Facades\RateLimiter::attempt($key, maxAttempts: 3, callback: static fn () => true, decaySeconds: 60)) {
            Notification::make()
                ->title('Please wait a moment before checking again.')
                ->body('Rate limit reached for this action. Try again in about one minute.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    protected function notify(string $message): void
    {
        Notification::make()->title($message)->success()->send();

        $this->refreshSite();
    }

    public function pollStatus(): void
    {
        $this->refreshSite();
        $this->pollingEnabled = $this->shouldAutoPollByStatus() || $this->pollingEnabled;
    }

    public function shouldPollStatus(): bool
    {
        return $this->pollingEnabled || $this->shouldAutoPollByStatus();
    }

    protected function refreshSite(): void
    {
        if (! $this->site) {
            return;
        }

        $this->site = Site::query()->find($this->site->id);

        if ($this->site) {
            $this->site->loadMissing('analyticsMetric');
        }
    }

    protected function shouldAutoPollByStatus(): bool
    {
        return in_array($this->site?->status, [
            Site::STATUS_PENDING_DNS_VALIDATION,
            Site::STATUS_DEPLOYING,
            Site::STATUS_READY_FOR_CUTOVER,
        ], true);
    }

    public function diagnosticsDetails(): array
    {
        if (! $this->site) {
            return [];
        }

        $lastSync = $this->site->analyticsMetric?->captured_at
            ?? $this->site->last_checked_at
            ?? $this->site->updated_at;

        $apiStatus = $this->site->last_error ? 'Error' : 'OK';
        $apiLatency = data_get($this->site->provider_meta, 'api_response_time_ms')
            ?? data_get($this->site->analyticsMetric?->source ?? [], 'response_time_ms');

        return [
            'edge_provider' => (string) ($this->site->provider ?? 'unknown'),
            'zone_id' => (string) ($this->site->provider_resource_id ?? data_get($this->site->provider_meta, 'zone_id', 'n/a')),
            'site_id' => (string) $this->site->id,
            'last_sync' => $lastSync?->toDateTimeString() ?? 'n/a',
            'api_status' => $apiStatus,
            'api_response_time' => $apiLatency !== null ? $apiLatency.' ms' : 'n/a',
            'raw_health' => (string) ($this->site->onboarding_status ?: $this->site->status ?: 'unknown'),
        ];
    }

    protected function runBunnyDnsCheckNow(): void
    {
        if (! $this->site) {
            return;
        }

        try {
            $job = new CheckAcmDnsValidationJob($this->site->id, auth()->id());
            $job->handle(app(EdgeProviderManager::class));
        } catch (\Throwable $e) {
            $this->site->update([
                'status' => Site::STATUS_FAILED,
                'onboarding_status' => Site::ONBOARDING_FAILED,
                'last_error' => $e->getMessage(),
                'last_checked_at' => now(),
            ]);
        } finally {
            $this->refreshSite();
        }
    }

    protected function bunnyCheckMessage(): string
    {
        return match ($this->site?->onboarding_status) {
            Site::ONBOARDING_LIVE => 'DNS and SSL are active. Protection is live.',
            Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING => 'DNS looks correct. SSL is still issuing, we will keep checking.',
            Site::ONBOARDING_PENDING_DNS_CUTOVER => 'DNS does not point to the Edge Network yet. Update records and check again.',
            Site::ONBOARDING_FAILED => 'Check failed. Review the latest error and retry.',
            default => 'DNS check completed.',
        };
    }
}
