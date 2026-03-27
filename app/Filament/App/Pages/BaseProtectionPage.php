<?php

namespace App\Filament\App\Pages;

use App\Jobs\ApplySiteControlSettingJob;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Jobs\InvalidateCloudFrontCacheJob;
use App\Jobs\MarkSiteReadyForCutoverJob;
use App\Jobs\ProvisionEdgeDeploymentJob;
use App\Jobs\RequestAcmCertificateJob;
use App\Jobs\ToggleTroubleshootingModeJob;
use App\Jobs\ToggleDevelopmentModeJob;
use App\Jobs\ToggleUnderAttackModeJob;
use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Billing\SiteBillingStateService;
use App\Services\DemoModeService;
use App\Services\Edge\EdgeProviderManager;
use App\Services\Firewall\FirewallInsightsPresenter;
use App\Services\SiteContext;
use App\Services\Sites\SiteRoutingStatusService;
use App\Services\UiModeManager;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

abstract class BaseProtectionPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Security & Protection';

    public ?Site $site = null;

    public ?int $selectedSiteId = null;

    public string $purgePath = '';

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

    public function isDemoReadOnly(): bool
    {
        return app(DemoModeService::class)->isReadOnlyDemoSite($this->site);
    }

    protected function ensureNotDemoReadOnly(string $action = 'This action'): bool
    {
        if (! $this->isDemoReadOnly()) {
            return true;
        }

        Notification::make()
            ->title('Demo environment is read-only.')
            ->body($action.' is disabled on demo.firephage.com. This dashboard is for review only.')
            ->warning()
            ->send();

        return false;
    }

    /**
     * @return array<int, array{label:string,value:string,support:string,help:string,color:string}>
     */
    public function simpleSecuritySnapshot(): array
    {
        if (! $this->site) {
            return [];
        }

        $this->site->loadMissing('analyticsMetric');

        $insightsPresenter = app(FirewallInsightsPresenter::class);
        $insights = $insightsPresenter->insights($this->site);
        $summary = (array) data_get($insights, 'summary', []);
        $totalRequests = (int) ($summary['total'] ?? ($this->site->analyticsMetric?->total_requests_24h ?? 0));
        $blockedRequests = (int) ($summary['blocked'] ?? ($this->site->analyticsMetric?->blocked_requests_24h ?? 0));
        $suspiciousRequests = $insightsPresenter->suspiciousRequests($insights);
        $threatLevel = $insightsPresenter->threatLevel($insights);
        $blockRatio = $totalRequests > 0
            ? (($blockedRequests / max(1, $totalRequests)) * 100)
            : 0.0;

        $threatColor = match ($threatLevel) {
            'Under Attack' => 'danger',
            'Active Mitigation' => 'warning',
            default => 'success',
        };

        return [
            [
                'label' => 'Threat Level',
                'value' => $threatLevel,
                'support' => match ($threatLevel) {
                    'Under Attack' => 'A high share of recent traffic is being blocked or challenged.',
                    'Active Mitigation' => 'Protection is actively filtering noisy or risky traffic.',
                    default => 'Recent traffic looks normal and protection is stable.',
                },
                'help' => 'This is a quick summary of how aggressive recent traffic looks, based mostly on how much traffic FirePhage had to block or challenge.',
                'color' => $threatColor,
            ],
            [
                'label' => 'Total Requests',
                'value' => number_format($totalRequests),
                'support' => 'Everything seen by the edge in the last 24 hours, including good and bad traffic.',
                'help' => 'Use this to understand overall traffic volume. It includes normal visitors, bots, and blocked requests together.',
                'color' => 'primary',
            ],
            [
                'label' => 'Blocked',
                'value' => number_format($blockedRequests),
                'support' => $totalRequests > 0
                    ? number_format($blockRatio, 2).'% of recent traffic was denied or challenged.'
                    : 'No recent blocked traffic has been recorded yet.',
                'help' => 'Blocked requests were denied outright or forced through a challenge because they matched a protection rule or looked abusive.',
                'color' => 'danger',
            ],
            [
                'label' => 'Suspicious',
                'value' => number_format($suspiciousRequests),
                'support' => 'Traffic that looks risky and deserves attention, even if it was not blocked.',
                'help' => 'Suspicious traffic is traffic with risk signals like attack paths or odd request behavior, even when it was not fully blocked.',
                'color' => 'warning',
            ],
            [
                'label' => 'Last Sync',
                'value' => $this->site->syncFreshnessForHumans('No sync yet'),
                'support' => 'Shows how fresh the edge telemetry is right now.',
                'help' => 'If sync freshness gets old, the numbers on this page may be stale and no longer reflect what is happening at the edge.',
                'color' => 'gray',
            ],
        ];
    }

    /**
     * @return array{title:string,body:string,color:string}
     */
    public function simpleSecurityRecommendation(): array
    {
        $snapshot = collect($this->simpleSecuritySnapshot())->keyBy('label');
        $threatLevel = (string) data_get($snapshot, 'Threat Level.value', 'Healthy');

        return match ($threatLevel) {
            'Under Attack' => [
                'title' => 'What to do now',
                'body' => 'Traffic pressure is elevated. Review blocked activity, confirm origin protection is enabled, and consider switching to Pro mode for deeper event detail.',
                'color' => 'danger',
            ],
            'Active Mitigation' => [
                'title' => 'What to watch',
                'body' => 'Protection is working. Keep an eye on blocked traffic and sync freshness so you can tell whether the spike is growing or settling down.',
                'color' => 'warning',
            ],
            default => [
                'title' => 'What this means',
                'body' => 'Protection looks healthy. Simple Mode keeps the key numbers front and center while the site stays quiet and stable.',
                'color' => 'success',
            ],
        };
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

        if (! $this->ensureNotDemoReadOnly('Provisioning')) {
            return;
        }

        if (! $this->ensureBillingReadyForProtectionAction('Provisioning')) {
            return;
        }

        $this->pollingEnabled = true;
        if (! $this->throttle('provision')) {
            return;
        }

        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            if ((int) ($this->site->provider_resource_id ?: data_get($this->site->provider_meta, 'zone_id', 0)) > 0) {
                try {
                    $result = app(EdgeProviderManager::class)->forSite($this->site)->checkSsl($this->site);
                    $this->refreshSite();
                    $this->notify((string) ($result['message'] ?? 'SSL status refreshed.'));
                } catch (\Throwable $exception) {
                    report($exception);
                    $this->notify('SSL refresh failed');
                }

                return;
            }

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

    public function refreshSslStatus(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('SSL status refresh')) {
            return;
        }

        try {
            $result = app(EdgeProviderManager::class)->forSite($this->site)->checkSsl($this->site);
            $this->refreshSite();
            $this->notify((string) ($result['message'] ?? 'SSL status refreshed.'));
        } catch (\Throwable $exception) {
            report($exception);
            $this->notify('SSL refresh failed');
        }
    }

    public function checkDnsValidation(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('DNS validation')) {
            return;
        }

        if (! $this->ensureBillingReadyForProtectionAction('DNS validation')) {
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

        if (! $this->ensureNotDemoReadOnly('Cutover checks')) {
            return;
        }

        if (! $this->ensureBillingReadyForProtectionAction('Cutover checks')) {
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

        if (! $this->ensureNotDemoReadOnly('Cache purge')) {
            return;
        }

        InvalidateCloudFrontCacheJob::dispatch($this->site->id, ['/*'], auth()->id());
        $this->notify('Cache purge queued');
    }

    public function purgeCachePath(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Path cache purge')) {
            return;
        }

        $path = trim($this->purgePath);
        if ($path === '') {
            Notification::make()->title('Enter a path to purge.')->warning()->send();

            return;
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        InvalidateCloudFrontCacheJob::dispatch($this->site->id, [$path], auth()->id());
        $this->purgePath = '';
        $this->notify('Path purge queued');
    }

    public function toggleUnderAttack(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Under Attack mode')) {
            return;
        }

        ToggleUnderAttackModeJob::dispatch($this->site->id, ! $this->site->under_attack, auth()->id());
        $this->notify('Firewall mode update queued');
    }

    public function toggleDevelopmentMode(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Development mode')) {
            return;
        }

        if (! $this->throttle('toggle-development-mode')) {
            return;
        }

        try {
            (new ToggleDevelopmentModeJob($this->site->id, ! (bool) $this->site->development_mode, auth()->id()))
                ->handle(app(\App\Services\Edge\EdgeProviderManager::class));

            $this->refreshSite();
            $this->notify($this->isDevelopmentMode() ? 'Development mode enabled' : 'Development mode disabled');
        } catch (\Throwable $exception) {
            report($exception);
            $this->notify('Development mode update failed');
        }
    }

    public function setDevelopmentModeState(bool $enabled): void
    {
        if (! $this->site) {
            return;
        }

        if ($this->isDevelopmentMode() === $enabled) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Development mode')) {
            return;
        }

        if (! $this->throttle('toggle-development-mode')) {
            return;
        }

        try {
            (new ToggleDevelopmentModeJob($this->site->id, $enabled, auth()->id()))
                ->handle(app(\App\Services\Edge\EdgeProviderManager::class));

            $this->refreshSite();
            $this->notify($enabled ? 'Development mode enabled' : 'Development mode disabled');
        } catch (\Throwable $exception) {
            report($exception);
            $this->notify('Development mode update failed');
        }
    }

    public function toggleTroubleshootingMode(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Troubleshooting mode')) {
            return;
        }

        if (! $this->throttle('toggle-troubleshooting-mode')) {
            return;
        }

        try {
            (new ToggleTroubleshootingModeJob($this->site->id, ! (bool) $this->site->troubleshooting_mode, auth()->id()))
                ->handle(app(\App\Services\Edge\EdgeProviderManager::class));

            $this->refreshSite();
            $this->notify($this->isTroubleshootingMode()
                ? 'Troubleshooting mode enabled'
                : 'Troubleshooting mode disabled');
        } catch (\Throwable $exception) {
            report($exception);
            $this->notify('Troubleshooting mode update failed');
        }
    }

    public function setTroubleshootingModeState(bool $enabled): void
    {
        if (! $this->site) {
            return;
        }

        if ($this->isTroubleshootingMode() === $enabled) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Troubleshooting mode')) {
            return;
        }

        if (! $this->throttle('toggle-troubleshooting-mode')) {
            return;
        }

        try {
            (new ToggleTroubleshootingModeJob($this->site->id, $enabled, auth()->id()))
                ->handle(app(\App\Services\Edge\EdgeProviderManager::class));

            $this->refreshSite();
            $this->notify($enabled
                ? 'Troubleshooting mode enabled'
                : 'Troubleshooting mode disabled');
        } catch (\Throwable $exception) {
            report($exception);
            $this->notify('Troubleshooting mode update failed');
        }
    }

    public function isDevelopmentMode(): bool
    {
        return (bool) ($this->site?->development_mode ?? false);
    }

    public function isTroubleshootingMode(): bool
    {
        return (bool) ($this->site?->troubleshooting_mode ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function edgeRoutingStatus(bool $fresh = false): array
    {
        if (! $this->site) {
            return [
                'status' => 'unavailable',
                'label' => 'Unavailable',
                'color' => 'gray',
                'message' => 'Select a site to check edge routing status.',
                'expected_target' => null,
                'checked_at' => null,
                'domains' => [],
            ];
        }

        return app(SiteRoutingStatusService::class)->statusForSite($this->site, $fresh);
    }

    public function refreshEdgeRoutingStatus(): void
    {
        if (! $this->site) {
            return;
        }

        app(SiteRoutingStatusService::class)->forget($this->site);
        $this->notify('Edge routing status refreshed');
    }

    public function shouldShowEdgeRoutingWarning(): bool
    {
        if (! $this->site) {
            return false;
        }

        return in_array($this->edgeRoutingStatus()['status'] ?? 'unavailable', ['drift', 'partial'], true);
    }

    public function edgeRoutingWarningMessage(): string
    {
        $status = $this->edgeRoutingStatus()['status'] ?? 'unavailable';

        return match ($status) {
            'partial' => 'Protection is partially inactive because some of your domain records are no longer pointed to our edge network. Update the DNS records below to restore protection, caching, and related services.',
            default => 'Protection is currently inactive because your domain is no longer pointed to our edge network. Update the DNS records below to restore protection, caching, and related services.',
        };
    }

    public function edgeRoutingWarningColor(): string
    {
        return ($this->edgeRoutingStatus()['status'] ?? null) === 'partial' ? 'warning' : 'danger';
    }

    public function edgeRoutingRecords(): array
    {
        return (array) data_get($this->site?->required_dns_records, 'traffic', []);
    }

    public function toggleHttpsEnforcement(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('HTTPS enforcement')) {
            return;
        }

        $this->applySiteControlImmediately(
            'https_enforced',
            ! $this->httpsEnforcementEnabled(),
            'HTTPS enforcement saved'
        );
    }

    public function toggleCacheMode(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Cache mode')) {
            return;
        }

        $mode = $this->cacheMode() === 'aggressive' ? 'standard' : 'aggressive';
        ApplySiteControlSettingJob::dispatch($this->site->id, 'cache_mode', $mode, auth()->id());
        $this->notify('Cache mode saved');
    }

    public function setCacheModeState(string $mode): void
    {
        if (! $this->site) {
            return;
        }

        $mode = strtolower(trim($mode));

        if (! in_array($mode, ['standard', 'aggressive'], true)) {
            return;
        }

        if ($this->cacheMode() === $mode) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Cache mode')) {
            return;
        }

        $this->applySiteControlImmediately('cache_mode', $mode, 'Cache mode saved');
    }

    public function toggleCacheEnabled(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Cache settings')) {
            return;
        }

        $current = (bool) data_get($this->site->required_dns_records, 'control_panel.cache_enabled', true);
        ApplySiteControlSettingJob::dispatch($this->site->id, 'cache_enabled', ! $current, auth()->id());
        $this->notify(! $current ? 'Cache enabled' : 'Cache disabled');
    }

    public function setCacheEnabledState(bool $enabled): void
    {
        if (! $this->site) {
            return;
        }

        if ($this->isCacheEnabled() === $enabled) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Cache settings')) {
            return;
        }

        $this->applySiteControlImmediately('cache_enabled', $enabled, $enabled ? 'Cache enabled' : 'Cache disabled');
    }

    /**
     * @return array<int, array{path_pattern:string,reason:string,enabled:bool}>
     */
    public function cacheExclusions(): array
    {
        return app(\App\Services\Bunny\BunnyGlobalDefaultsService::class)->cacheExclusionsForSite($this->site);
    }

    public function toggleCacheExclusion(string $pathPattern): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Cache bypass rules')) {
            return;
        }

        $pathPattern = trim($pathPattern);
        if ($pathPattern === '') {
            return;
        }

        $updated = collect($this->cacheExclusions())
            ->map(function (array $row) use ($pathPattern): array {
                if ((string) ($row['path_pattern'] ?? '') !== $pathPattern) {
                    return $row;
                }

                $row['enabled'] = ! (bool) ($row['enabled'] ?? false);

                return $row;
            })
            ->values()
            ->all();

        ApplySiteControlSettingJob::dispatch($this->site->id, 'cache_exclusions', $updated, auth()->id());
        $this->notify('Cache bypass rules saved');
    }

    public function setCacheExclusionState(string $pathPattern, bool $enabled): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Cache bypass rules')) {
            return;
        }

        $pathPattern = trim($pathPattern);
        if ($pathPattern === '') {
            return;
        }

        $updated = collect($this->cacheExclusions())
            ->map(function (array $row) use ($pathPattern, $enabled): array {
                if ((string) ($row['path_pattern'] ?? '') !== $pathPattern) {
                    return $row;
                }

                $row['enabled'] = $enabled;

                return $row;
            })
            ->values()
            ->all();

        $this->applySiteControlImmediately('cache_exclusions', $updated, 'Cache bypass rules saved');
    }

    public function toggleOriginProtection(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Origin protection')) {
            return;
        }

        $current = (bool) data_get($this->site->required_dns_records, 'control_panel.origin_lockdown', false);
        ApplySiteControlSettingJob::dispatch($this->site->id, 'origin_lockdown', ! $current, auth()->id());
        $this->notify('Origin access policy saved in FirePhage');
    }

    public function certificateStatus(): string
    {
        if ($this->site?->provider === Site::PROVIDER_BUNNY) {
            $hostnames = $this->sslHostnames();

            if ($hostnames !== [] && collect($hostnames)->every(fn (array $row): bool => (bool) ($row['is_valid'] ?? false))) {
                return 'Active';
            }

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

    public function sslHostnames(): array
    {
        return collect((array) data_get($this->site?->provider_meta, 'ssl.hostnames', []))
            ->map(function (array $row): array {
                return [
                    'hostname' => (string) ($row['hostname'] ?? ''),
                    'certificate_status' => (string) ($row['certificate_status'] ?? 'pending'),
                    'is_valid' => (bool) ($row['is_valid'] ?? false),
                    'has_certificate' => (bool) ($row['has_certificate'] ?? false),
                ];
            })
            ->filter(fn (array $row): bool => $row['hostname'] !== '')
            ->values()
            ->all();
    }

    public function tls10Enabled(): bool
    {
        return (bool) data_get($this->site?->required_dns_records, 'control_panel.tls1_enabled', data_get($this->site?->provider_meta, 'ssl.enable_tls1', false));
    }

    public function tls11Enabled(): bool
    {
        return (bool) data_get($this->site?->required_dns_records, 'control_panel.tls1_1_enabled', data_get($this->site?->provider_meta, 'ssl.enable_tls1_1', false));
    }

    public function toggleTls10(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('TLS 1.0 compatibility')) {
            return;
        }

        $this->applySiteControlImmediately(
            'tls1_enabled',
            ! $this->tls10Enabled(),
            'TLS 1.0 compatibility saved'
        );
    }

    public function toggleTls11(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('TLS 1.1 compatibility')) {
            return;
        }

        $this->applySiteControlImmediately(
            'tls1_1_enabled',
            ! $this->tls11Enabled(),
            'TLS 1.1 compatibility saved'
        );
    }

    public function sslManagedBy(): string
    {
        return $this->site?->provider === Site::PROVIDER_BUNNY ? 'Edge-managed SSL' : 'FirePhage managed SSL';
    }

    public function sslAutoModeLabel(): string
    {
        return (bool) data_get($this->site?->provider_meta, 'ssl.enable_auto_ssl', $this->site?->provider === Site::PROVIDER_BUNNY)
            ? 'Automatic'
            : 'Manual';
    }

    public function sslOriginVerificationLabel(): string
    {
        return $this->originSslVerificationEnabled()
            ? 'Enabled'
            : 'Disabled';
    }

    public function httpsEnforcementLabel(): string
    {
        return $this->httpsEnforcementEnabled()
            ? 'Enabled'
            : 'Disabled';
    }

    public function httpsEnforcementEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.https_enforced',
            data_get($this->site?->provider_meta, 'ssl.force_ssl_enabled', true)
        );
    }

    public function originSslVerificationEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_ssl_verification',
            data_get($this->site?->provider_meta, 'ssl.verify_origin_ssl', false)
        );
    }

    public function toggleOriginSslVerification(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Origin certificate verification')) {
            return;
        }

        $this->applySiteControlImmediately(
            'origin_ssl_verification',
            ! $this->originSslVerificationEnabled(),
            'Origin certificate verification saved'
        );
    }

    protected function applySiteControlImmediately(string $setting, mixed $value, string $successMessage): void
    {
        if (! $this->site) {
            return;
        }

        try {
            (new ApplySiteControlSettingJob($this->site->id, $setting, $value, auth()->id()))
                ->handle(app(EdgeProviderManager::class));

            $this->refreshSite();
            $this->notify($successMessage);
        } catch (\Throwable $exception) {
            report($exception);
            $this->notify('Setting update failed');
        }
    }

    public function cacheMode(): string
    {
        return (string) data_get($this->site?->required_dns_records, 'control_panel.cache_mode', 'standard');
    }

    public function isCacheEnabled(): bool
    {
        return (bool) data_get($this->site?->required_dns_records, 'control_panel.cache_enabled', true);
    }

    public function browserCacheTtl(): int
    {
        return (int) data_get($this->site?->required_dns_records, 'control_panel.browser_cache_ttl', -1);
    }

    public function browserCacheTtlLabel(): string
    {
        return match ($this->browserCacheTtl()) {
            -1 => 'Respect origin',
            0 => 'Disabled',
            300 => '5 minutes',
            3600 => '1 hour',
            14400 => '4 hours',
            86400 => '1 day',
            604800 => '7 days',
            default => 'Custom',
        };
    }

    public function cycleBrowserCacheTtl(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Browser cache policy')) {
            return;
        }

        $options = [-1, 300, 3600, 14400, 86400, 604800, 0];
        $current = $this->browserCacheTtl();
        $index = array_search($current, $options, true);
        $next = $options[$index === false ? 0 : (($index + 1) % count($options))];

        ApplySiteControlSettingJob::dispatch($this->site->id, 'browser_cache_ttl', $next, auth()->id());
        $this->notify('Browser cache policy saved');
    }

    public function setBrowserCacheTtl(int $ttl): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Browser cache policy')) {
            return;
        }

        $allowed = [-1, 0, 300, 3600, 14400, 86400, 604800];

        if (! in_array($ttl, $allowed, true)) {
            return;
        }

        ApplySiteControlSettingJob::dispatch($this->site->id, 'browser_cache_ttl', $ttl, auth()->id());
        $this->notify('Browser cache policy saved');
    }

    public function queryStringPolicy(): string
    {
        return (string) data_get($this->site?->required_dns_records, 'control_panel.query_string_policy', 'ignore');
    }

    public function queryStringPolicyLabel(): string
    {
        return $this->queryStringPolicy() === 'include'
            ? 'Include query strings'
            : 'Ignore query strings';
    }

    public function toggleQueryStringPolicy(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Query string policy')) {
            return;
        }

        $next = $this->queryStringPolicy() === 'include' ? 'ignore' : 'include';
        ApplySiteControlSettingJob::dispatch($this->site->id, 'query_string_policy', $next, auth()->id());
        $this->notify('Query string cache policy saved');
    }

    public function setQueryStringPolicyState(string $policy): void
    {
        if (! $this->site) {
            return;
        }

        $policy = strtolower(trim($policy));

        if (! in_array($policy, ['include', 'ignore'], true)) {
            return;
        }

        if ($this->queryStringPolicy() === $policy) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Query string policy')) {
            return;
        }

        $this->applySiteControlImmediately('query_string_policy', $policy, 'Query string cache policy saved');
    }

    public function optimizerMinifyCssEnabled(): bool
    {
        return (bool) data_get($this->site?->required_dns_records, 'control_panel.optimizer_minify_css', true);
    }

    public function optimizerMinifyJsEnabled(): bool
    {
        return (bool) data_get($this->site?->required_dns_records, 'control_panel.optimizer_minify_js', true);
    }

    public function optimizerImagesEnabled(): bool
    {
        return (bool) data_get($this->site?->required_dns_records, 'control_panel.optimizer_images', true);
    }

    public function toggleOptimizerMinifyCss(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('CSS optimization')) {
            return;
        }

        ApplySiteControlSettingJob::dispatch($this->site->id, 'optimizer_minify_css', ! $this->optimizerMinifyCssEnabled(), auth()->id());
        $this->notify('CSS minification saved');
    }

    public function setOptimizerMinifyCssState(bool $enabled): void
    {
        if (! $this->site) {
            return;
        }

        if ($this->optimizerMinifyCssEnabled() === $enabled) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('CSS optimization')) {
            return;
        }

        $this->applySiteControlImmediately('optimizer_minify_css', $enabled, 'CSS minification saved');
    }

    public function toggleOptimizerMinifyJs(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('JavaScript optimization')) {
            return;
        }

        ApplySiteControlSettingJob::dispatch($this->site->id, 'optimizer_minify_js', ! $this->optimizerMinifyJsEnabled(), auth()->id());
        $this->notify('JavaScript minification saved');
    }

    public function setOptimizerMinifyJsState(bool $enabled): void
    {
        if (! $this->site) {
            return;
        }

        if ($this->optimizerMinifyJsEnabled() === $enabled) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('JavaScript optimization')) {
            return;
        }

        $this->applySiteControlImmediately('optimizer_minify_js', $enabled, 'JavaScript minification saved');
    }

    public function toggleOptimizerImages(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Image optimization')) {
            return;
        }

        ApplySiteControlSettingJob::dispatch($this->site->id, 'optimizer_images', ! $this->optimizerImagesEnabled(), auth()->id());
        $this->notify('Image optimization saved');
    }

    public function setOptimizerImagesState(bool $enabled): void
    {
        if (! $this->site) {
            return;
        }

        if ($this->optimizerImagesEnabled() === $enabled) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Image optimization')) {
            return;
        }

        $this->applySiteControlImmediately('optimizer_images', $enabled, 'Image optimization saved');
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

    public function lastAction(string|array $startsWith): string
    {
        if (! $this->site) {
            return 'No recent actions';
        }

        $prefixes = collect(is_array($startsWith) ? $startsWith : [$startsWith])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values();

        if ($prefixes->isEmpty()) {
            return 'No recent actions';
        }

        $log = AuditLog::query()
            ->where('site_id', $this->site->id)
            ->where(function ($query) use ($prefixes): void {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('action', 'like', $prefix.'%');
                }
            })
            ->latest('id')
            ->first();

        if (! $log) {
            return 'No recent actions';
        }

        return $this->formatAuditActionLabel((string) $log->action).' · '.$log->status;
    }

    protected function formatAuditActionLabel(string $action): string
    {
        return match (true) {
            Str::startsWith($action, 'edge.cache_purge'),
            Str::startsWith($action, 'cloudfront.invalidate') => 'Cache purge',
            Str::startsWith($action, 'edge.development_mode') => 'Development mode',
            Str::startsWith($action, 'edge.troubleshooting_mode') => 'Troubleshooting mode',
            Str::startsWith($action, 'site.control.cache_enabled') => 'Cache setting',
            Str::startsWith($action, 'site.control.cache_mode') => 'Cache mode',
            Str::startsWith($action, 'site.control.tls1_enabled') => 'TLS 1.0 compatibility',
            Str::startsWith($action, 'site.control.tls1_1_enabled') => 'TLS 1.1 compatibility',
            Str::startsWith($action, 'site.control.origin_ssl_verification') => 'Origin certificate verification',
            Str::startsWith($action, 'site.control.origin_lockdown') => 'Origin access policy',
            Str::startsWith($action, 'site.control.https_enforced') => 'HTTPS enforcement',
            Str::startsWith($action, 'site.control.waf_preset') => 'WAF preset',
            default => Str::of($action)->replace('.', ' ')->title()->toString(),
        };
    }

    public function badgeColor(?string $status = null, ?Site $site = null): string
    {
        $site ??= $this->site;
        $status ??= $site?->status;

        if ($site && $this->routingDisplayState($site) === 'drift') {
            return 'danger';
        }

        if ($site && $this->routingDisplayState($site) === 'partial') {
            return 'warning';
        }

        return match ($status) {
            Site::STATUS_ACTIVE => 'success',
            Site::STATUS_PENDING_DNS_VALIDATION, Site::STATUS_DEPLOYING, Site::STATUS_READY_FOR_CUTOVER => 'warning',
            Site::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    public function statusLabel(?string $status = null, ?Site $site = null): string
    {
        $site ??= $this->site;
        $status ??= $site?->status;

        return match ($this->routingDisplayState($site)) {
            'drift' => 'Protection Inactive',
            'partial' => 'Partially Protected',
            default => Site::statuses()[$status] ?? str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    protected function routingDisplayState(?Site $site): ?string
    {
        if (! $site) {
            return null;
        }

        $isLive = $site->status === Site::STATUS_ACTIVE
            || $site->onboarding_status === Site::ONBOARDING_LIVE;

        if (! $isLive) {
            return null;
        }

        $status = app(SiteRoutingStatusService::class)->statusForSite($site)['status'] ?? null;

        return in_array($status, ['drift', 'partial'], true) ? $status : null;
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
        if ($user = auth()->user()) {
            FilamentNotification::make()
                ->title($message)
                ->success()
                ->sendToDatabase($user);
        }

        Notification::make()->title($message)->success()->send();

        $this->refreshSite();
    }

    protected function ensureBillingReadyForProtectionAction(string $action): bool
    {
        if (! $this->site) {
            return false;
        }

        $billing = app(SiteBillingStateService::class);

        if ($billing->canProgressProtection($this->site)) {
            return true;
        }

        Notification::make()
            ->title('Billing action required')
            ->body($billing->blockedActionMessage($this->site, $action))
            ->warning()
            ->send();

        return false;
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
            'edge_provider' => 'Managed',
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
