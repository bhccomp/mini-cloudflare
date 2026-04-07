<?php

namespace App\Services\Edge\Providers;

use App\Models\Site;
use App\Models\SystemSetting;
use App\Services\Bunny\BunnyEdgeErrorPageService;
use App\Services\Bunny\BunnyGlobalDefaultsService;
use App\Services\Bunny\BunnyShieldAccessListService;
use App\Services\Bunny\BunnyShieldSecurityService;
use App\Services\Bunny\Waf\BunnyShieldWafService;
use App\Services\Billing\OrganizationEntitlementService;
use App\Services\Dns\CloudflareDnsService;
use App\Services\Edge\EdgeProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BunnyCdnProvider implements EdgeProviderInterface
{
    public function __construct(
        protected ?BunnyShieldAccessListService $shieldAccess = null,
        protected ?BunnyShieldSecurityService $shieldSecurity = null,
        protected ?BunnyEdgeErrorPageService $edgeErrorPages = null,
        protected ?CloudflareDnsService $cloudflareDns = null,
        protected ?BunnyShieldWafService $shieldWaf = null,
    ) {}

    public function key(): string
    {
        return Site::PROVIDER_BUNNY;
    }

    public function requiresCertificateValidation(): bool
    {
        return false;
    }

    public function requestCertificate(Site $site): array
    {
        $existingShieldZoneId = (int) data_get($site->provider_meta, 'shield_zone_id', 0);
        $resolvedShieldZoneId = $shieldZoneId ?: ($existingShieldZoneId > 0 ? $existingShieldZoneId : null);

        return [
            'changed' => false,
            'required_dns_records' => $site->required_dns_records ?? [],
            'message' => 'Bunny provider does not require ACM DNS validation before deployment.',
        ];
    }

    public function checkCertificateValidation(Site $site): array
    {
        return [
            'validated' => true,
            'required_dns_records' => $site->required_dns_records ?? [],
            'message' => 'Bunny provider is ready for deployment.',
        ];
    }

    public function provision(Site $site): array
    {
        $originUrl = $this->resolvePreferredOriginUrl($site);
        $originHostHeader = $this->preferredOriginHostHeader($site);
        $logging = $this->loggingSettingsPayload();

        $existingId = (int) ($site->provider_resource_id ?: 0);
        $zoneId = $existingId;
        $zoneName = (string) data_get($site->provider_meta, 'zone_name', '');

        if ($zoneId <= 0) {
            $zoneName = $this->zoneNameFor($site);

            $created = $this->client()->post('/pullzone', [
                'Name' => $zoneName,
                'OriginUrl' => $originUrl,
                'OriginHostHeader' => $originHostHeader,
                'AddHostHeader' => true,
                'EnableAutoSSL' => true,
                'Type' => 0,
            ] + $logging)->throw()->json();

            $zoneId = (int) (Arr::get($created, 'Id') ?? Arr::get($created, 'id') ?? 0);
            $zoneName = (string) (Arr::get($created, 'Name') ?? $zoneName);
        }

        $this->syncZoneOriginSettings($zoneId, $zoneName, $originUrl, $originHostHeader);

        $shieldZoneId = null;
        $shieldMessage = null;
        $shieldPlanStatus = 'inactive';
        $shieldPlanMessage = null;
        try {
            $site->forceFill([
                'provider_resource_id' => (string) $zoneId,
                'provider_meta' => array_merge(
                    is_array($site->provider_meta) ? $site->provider_meta : [],
                    ['zone_id' => $zoneId, 'zone_name' => $zoneName],
                ),
            ])->save();

            $shieldZoneId = $this->shieldAccess()->ensureShieldZone($site);

            if ($this->shouldUseAdvancedShield($site)) {
                $planResult = $this->shieldSecurity()->ensureAdvancedPlan($site, $shieldZoneId);
                $shieldPlanStatus = ((bool) ($planResult['changed'] ?? false)) ? 'active' : 'unchanged';
                $shieldPlanMessage = (string) ($planResult['message'] ?? 'Bunny Shield advanced plan is enabled.');
            } elseif ($shieldZoneId) {
                $planState = $this->shieldSecurity()->currentPlanState($shieldZoneId);

                if ((int) ($planState['plan_type'] ?? 0) > 0 || (bool) ($planState['premium_plan'] ?? false) || (bool) ($planState['whitelabel_response_pages'] ?? false)) {
                    $planResult = $this->shieldSecurity()->downgradePlan($site, $shieldZoneId);
                    $shieldPlanStatus = ((bool) ($planResult['changed'] ?? false)) ? 'downgraded' : 'unchanged';
                    $shieldPlanMessage = (string) ($planResult['message'] ?? 'Bunny Shield is using the basic plan for this site.');
                } else {
                    $shieldPlanStatus = 'basic';
                    $shieldPlanMessage = 'Bunny Shield is using the basic plan for this site.';
                }
            }

            app(BunnyGlobalDefaultsService::class)->syncSecurityDefaults($site);
            $this->syncManagedCacheExclusions($site);
        } catch (\Throwable $e) {
            $shieldMessage = $e->getMessage();
            $shieldPlanStatus = 'failed';
            $shieldPlanMessage = $e->getMessage();
        }

        $edgeScriptStatus = 'inactive';
        $edgeScriptId = null;
        $edgeScriptMessage = 'Origin custom error pages are currently disabled.';
        $edgeScriptLastError = $edgeScriptMessage;

        if ($this->shouldUseAdvancedShield($site)) {
            try {
                $edgeScriptResult = $this->syncEdgeErrorPages($site);
                $edgeScriptStatus = (string) ($edgeScriptResult['status'] ?? 'active');
                $edgeScriptId = (int) ($edgeScriptResult['script_id'] ?? 0) ?: null;
                $edgeScriptMessage = (string) ($edgeScriptResult['message'] ?? 'Branded edge error pages are attached to this Bunny zone.');
                $edgeScriptLastError = null;
            } catch (\Throwable $e) {
                $edgeScriptStatus = 'failed';
                $edgeScriptMessage = $e->getMessage();
                $edgeScriptLastError = $edgeScriptMessage;
            }
        }

        $edgeDomain = $this->zoneEdgeDomain($zoneName);
        $customerEdgeDomain = $edgeDomain;
        $customerEdgeAliasRecordId = null;

        if ($this->cloudflareDns()->isConfigured()) {
            $customerEdgeAlias = $this->customerEdgeAliasHostname($site);
            $aliasRecord = $this->cloudflareDns()->upsertEdgeAlias($customerEdgeAlias, $edgeDomain);

            $customerEdgeDomain = $aliasRecord['hostname'];
            $customerEdgeAliasRecordId = $aliasRecord['id'];
        }

        $hostnames = [$site->apex_domain];

        if ($site->www_enabled && $site->www_domain) {
            $hostnames[] = $site->www_domain;
        }

        $hostnameResults = [];
        foreach ($hostnames as $hostname) {
            $response = $this->client()->post("/pullzone/{$zoneId}/addHostname", [
                'Hostname' => $hostname,
            ]);

            $this->requestFreeCertificate($hostname);

            $hostnameResults[] = [
                'hostname' => $hostname,
                'ok' => $response->successful(),
                'status' => $response->status(),
            ];
        }

        $requiredDnsRecords = [
            'traffic' => $this->trafficRecords($site, $customerEdgeDomain),
        ];

        $existingShieldZoneId = (int) data_get($site->provider_meta, 'shield_zone_id', 0);
        $resolvedShieldZoneId = $shieldZoneId ?: ($existingShieldZoneId > 0 ? $existingShieldZoneId : null);

        return [
            'ok' => true,
            'status' => Site::STATUS_DEPLOYING,
            'provider' => $this->key(),
            'provider_resource_id' => (string) $zoneId,
            'provider_meta' => [
                'zone_id' => $zoneId,
                'zone_name' => $zoneName,
                'shield_zone_id' => $resolvedShieldZoneId,
                'shield_status' => $resolvedShieldZoneId ? 'active' : 'pending',
                'shield_last_error' => $shieldMessage,
                'shield_plan_status' => $shieldPlanStatus,
                'shield_plan_message' => $shieldPlanMessage,
                'edge_error_script_id' => $edgeScriptId,
                'edge_error_script_status' => $edgeScriptStatus,
                'edge_error_script_last_error' => $edgeScriptLastError,
                'edge_domain' => $edgeDomain,
                'customer_edge_domain' => $customerEdgeDomain,
                'customer_edge_alias_record_id' => $customerEdgeAliasRecordId,
                'origin_url' => $originUrl,
                'origin_host_header' => $originHostHeader,
                'logging_enabled' => (bool) ($logging['EnableLogging'] ?? false),
                'logging_save_to_storage' => (bool) ($logging['LoggingSaveToStorage'] ?? false),
                'logging_storage_zone_id' => (int) ($logging['LoggingStorageZoneId'] ?? 0),
                'hostnames' => $hostnameResults,
            ],
            'distribution_id' => (string) $zoneId,
            'distribution_domain_name' => $customerEdgeDomain,
            'required_dns_records' => $requiredDnsRecords,
            'dns_records' => $requiredDnsRecords['traffic'],
            'notes' => array_values(array_filter([
                'Edge zone provisioned. Point DNS traffic records to the FirePhage edge hostname.',
                $resolvedShieldZoneId ? 'Security zone has been enabled for this site.' : null,
                $resolvedShieldZoneId ? null : 'Security zone setup is pending and will retry automatically.',
                $shieldPlanStatus === 'active' ? 'Bunny Shield advanced plan has been enabled for this site.' : null,
                $shieldPlanStatus === 'unchanged' ? $shieldPlanMessage : null,
                $shieldPlanStatus === 'failed' ? 'Bunny Shield advanced plan could not be enabled automatically yet.' : null,
                $edgeScriptStatus === 'active' ? 'Branded blocked pages are enabled for this site.' : null,
                $edgeScriptStatus === 'failed' ? 'Branded blocked pages could not be enabled automatically yet.' : null,
                $edgeScriptStatus === 'inactive' ? 'Origin custom error pages are disabled for this site.' : null,
            ])),
        ];
    }

    public function syncSiteFromProvider(Site $site): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            throw new \RuntimeException('This site does not have a Bunny pull zone yet.');
        }

        $zone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();
        $required = is_array($site->required_dns_records) ? $site->required_dns_records : [];
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];

        $zoneName = (string) (Arr::get($zone, 'Name') ?: data_get($meta, 'zone_name', $this->zoneNameFor($site)));
        $edgeDomain = (string) (Arr::get($zone, 'Host') ?: data_get($meta, 'edge_domain', $this->zoneEdgeDomain($zoneName)));
        $customerEdgeDomain = (string) data_get($meta, 'customer_edge_domain', '');

        if ($customerEdgeDomain === '') {
            $customerEdgeDomain = $edgeDomain;
        }

        $cacheExclusions = $this->managedCacheExclusionsFromZone($zone);
        $existingControls = $this->controlPanelState($site);
        $controls = array_merge($existingControls, [
            'cache_enabled' => ! (bool) Arr::get($zone, 'DisableCache', false),
            'cache_mode' => ((bool) Arr::get($zone, 'EnableQueryStringOrdering', false) || (bool) Arr::get($zone, 'EnableSmartCache', false))
                ? 'aggressive'
                : 'standard',
            'https_enforced' => $this->allTrackedHostnamesForceSsl($site, $zone),
            'cache_exclusions' => $cacheExclusions === [] ? $existingControls['cache_exclusions'] : $cacheExclusions,
            'browser_cache_ttl' => $this->normalizeBrowserCacheTtl(Arr::get($zone, 'CacheControlPublicMaxAgeOverride', -1)),
            'query_string_policy' => (bool) Arr::get($zone, 'IgnoreQueryStrings', true) ? 'ignore' : 'include',
            'optimizer_minify_css' => (bool) Arr::get($zone, 'OptimizerMinifyCSS', true),
            'optimizer_minify_js' => (bool) Arr::get($zone, 'OptimizerMinifyJavaScript', true),
            'optimizer_images' => (bool) (
                Arr::get($zone, 'OptimizerAutomaticOptimizationEnabled')
                ?? Arr::get($zone, 'OptimizerEnabled')
                ?? true
            ),
            'tls1_enabled' => (bool) Arr::get($zone, 'EnableTLS1', false),
            'tls1_1_enabled' => (bool) Arr::get($zone, 'EnableTLS1_1', false),
            'origin_ssl_verification' => (bool) Arr::get($zone, 'VerifyOriginSSL', false),
            'origin_host_header' => $this->normalizeOriginHostHeader(
                (string) Arr::get($zone, 'OriginHostHeader', data_get($meta, 'origin_host_header', '')),
                $site,
            ),
            'request_coalescing_enabled' => (bool) Arr::get($zone, 'EnableRequestCoalescing', true),
            'request_coalescing_timeout' => $this->normalizeRequestCoalescingTimeout(Arr::get($zone, 'RequestCoalescingTimeout', 30)),
            'origin_retries' => $this->normalizeOriginRetries(Arr::get($zone, 'OriginRetries', 1)),
            'origin_connect_timeout' => $this->normalizeOriginConnectTimeout(Arr::get($zone, 'OriginConnectTimeout', 10)),
            'origin_response_timeout' => $this->normalizeOriginResponseTimeout(Arr::get($zone, 'OriginResponseTimeout', 60)),
            'origin_retry_delay' => $this->normalizeOriginRetryDelay(Arr::get($zone, 'OriginRetryDelay', 1)),
            'origin_retry_5xx' => (bool) Arr::get($zone, 'OriginRetry5XXResponses', true),
            'origin_retry_connection_timeout' => (bool) Arr::get($zone, 'OriginRetryConnectionTimeout', true),
            'origin_retry_response_timeout' => (bool) Arr::get($zone, 'OriginRetryResponseTimeout', false),
            'stale_while_updating' => (bool) Arr::get($zone, 'UseStaleWhileUpdating', true),
            'stale_while_offline' => (bool) Arr::get($zone, 'UseStaleWhileOffline', true),
        ]);

        data_set($required, 'traffic', $this->trafficRecords($site, $customerEdgeDomain));
        data_set($required, 'control_panel', $controls);

        $meta['zone_id'] = $zoneId;
        $meta['zone_name'] = $zoneName;
        $meta['edge_domain'] = $edgeDomain;
        $meta['origin_url'] = (string) Arr::get($zone, 'OriginUrl', data_get($meta, 'origin_url', $site->origin_url));
        $meta['origin_host_header'] = $controls['origin_host_header'];
        $meta['hostnames'] = collect((array) Arr::get($zone, 'Hostnames', []))
            ->map(function (array $row): array {
                $hostname = (string) Arr::get($row, 'Value', Arr::get($row, 'Hostname', ''));

                return [
                    'hostname' => strtolower($hostname),
                    'ok' => trim($hostname) !== '',
                    'status' => 204,
                    'force_ssl' => (bool) Arr::get($row, 'ForceSSL', false),
                ];
            })
            ->filter(fn (array $row): bool => $row['hostname'] !== '')
            ->values()
            ->all();
        $meta['ssl'] = [
            'enable_auto_ssl' => (bool) Arr::get($zone, 'EnableAutoSSL', true),
            'enable_tls1' => (bool) Arr::get($zone, 'EnableTLS1', false),
            'enable_tls1_1' => (bool) Arr::get($zone, 'EnableTLS1_1', false),
            'verify_origin_ssl' => (bool) Arr::get($zone, 'VerifyOriginSSL', false),
            'force_ssl_enabled' => $controls['https_enforced'],
            'force_ssl_hostnames' => $this->trackedHostnameForceSslStates($site, $zone),
        ];
        $meta['zone_settings'] = [
            'OriginHostHeader' => $controls['origin_host_header'],
            'DisableCache' => (bool) Arr::get($zone, 'DisableCache', false),
            'EnableOptimizers' => (bool) Arr::get($zone, 'EnableOptimizers', true),
            'EnableQueryStringOrdering' => (bool) Arr::get($zone, 'EnableQueryStringOrdering', false),
            'EnableSmartCache' => (bool) Arr::get($zone, 'EnableSmartCache', false),
            'IgnoreQueryStrings' => (bool) Arr::get($zone, 'IgnoreQueryStrings', true),
            'CacheControlMaxAgeOverride' => (int) Arr::get($zone, 'CacheControlMaxAgeOverride', -1),
            'CacheControlPublicMaxAgeOverride' => (int) Arr::get($zone, 'CacheControlPublicMaxAgeOverride', -1),
            'OptimizerEnabled' => (bool) Arr::get($zone, 'OptimizerEnabled', true),
            'OptimizerAutomaticOptimizationEnabled' => (bool) Arr::get($zone, 'OptimizerAutomaticOptimizationEnabled', true),
            'OptimizerMinifyCSS' => (bool) Arr::get($zone, 'OptimizerMinifyCSS', true),
            'OptimizerMinifyJavaScript' => (bool) Arr::get($zone, 'OptimizerMinifyJavaScript', true),
            'EnableAutoSSL' => (bool) Arr::get($zone, 'EnableAutoSSL', true),
            'EnableTLS1' => (bool) Arr::get($zone, 'EnableTLS1', false),
            'EnableTLS1_1' => (bool) Arr::get($zone, 'EnableTLS1_1', false),
            'VerifyOriginSSL' => (bool) Arr::get($zone, 'VerifyOriginSSL', false),
            'EnableRequestCoalescing' => (bool) Arr::get($zone, 'EnableRequestCoalescing', true),
            'RequestCoalescingTimeout' => (int) Arr::get($zone, 'RequestCoalescingTimeout', 30),
            'OriginRetries' => (int) Arr::get($zone, 'OriginRetries', 1),
            'OriginConnectTimeout' => (int) Arr::get($zone, 'OriginConnectTimeout', 10),
            'OriginResponseTimeout' => (int) Arr::get($zone, 'OriginResponseTimeout', 60),
            'OriginRetryDelay' => (int) Arr::get($zone, 'OriginRetryDelay', 1),
            'OriginRetry5XXResponses' => (bool) Arr::get($zone, 'OriginRetry5XXResponses', true),
            'OriginRetryConnectionTimeout' => (bool) Arr::get($zone, 'OriginRetryConnectionTimeout', true),
            'OriginRetryResponseTimeout' => (bool) Arr::get($zone, 'OriginRetryResponseTimeout', false),
            'UseStaleWhileUpdating' => (bool) Arr::get($zone, 'UseStaleWhileUpdating', true),
            'UseStaleWhileOffline' => (bool) Arr::get($zone, 'UseStaleWhileOffline', true),
            'DisableCookies' => (bool) Arr::get($zone, 'DisableCookies', false),
            'EnableCookieVary' => (bool) Arr::get($zone, 'EnableCookieVary', false),
            'ErrorPageEnableCustomCode' => (bool) Arr::get($zone, 'ErrorPageEnableCustomCode', false),
            'ErrorPageCustomCodeConfigured' => filled((string) Arr::get($zone, 'ErrorPageCustomCode', '')),
        ];
        $meta['cache_exclusion_rules'] = [
            'count' => count($controls['cache_exclusions']),
            'rules' => $controls['cache_exclusions'],
        ];
        $meta['control_panel'] = $controls;
        $meta['synced_from_bunny_at'] = now()->toIso8601String();

        try {
            $meta['shield_settings'] = $this->shieldSecurity()->currentSettings($site);
        } catch (\Throwable) {
            // Keep sync resilient even if Shield API data is temporarily unavailable.
        }

        try {
            $meta['bot_detection'] = $this->shieldWaf()->botDetectionSettings($site);
        } catch (\Throwable) {
            // Keep sync resilient even if bot detection data is temporarily unavailable.
        }

        $site->forceFill([
            'required_dns_records' => $required,
            'provider_meta' => $meta,
            'cloudfront_domain_name' => $customerEdgeDomain,
        ])->save();

        return [
            'zone_id' => $zoneId,
            'domain' => $site->apex_domain,
            'customer_edge_domain' => $customerEdgeDomain,
            'message' => 'Live Bunny settings were synced into FirePhage.',
        ];
    }

    private function buildOriginUrl(Site $site): string
    {
        $originIp = trim((string) ($site->origin_ip ?? ''));

        if ($originIp !== '') {
            return 'http://'.$originIp;
        }

        $fallback = trim((string) ($site->origin_url ?? ''));

        if ($fallback === '') {
            throw new \RuntimeException('Origin / Server IP is required before provisioning Bunny edge.');
        }

        return $fallback;
    }

    private function shouldUseAdvancedShield(Site $site): bool
    {
        if (! (bool) config('edge.bunny.shield_auto_upgrade_to_advanced', false)) {
            return false;
        }

        return app(OrganizationEntitlementService::class)->shouldUseAdvancedShield($site);
    }

    private function resolvePreferredOriginUrl(Site $site): string
    {
        $originIp = trim((string) ($site->origin_ip ?? ''));
        $domain = $this->preferredOriginHostHeader($site);

        if ($originIp === '' || $domain === '') {
            return $this->buildOriginUrl($site);
        }

        $http = $this->probeOriginEndpoint($originIp, $domain, 'http');
        $https = $this->probeOriginEndpoint($originIp, $domain, 'https');

        if ($this->isCanonicalHttpsRedirect($http, $domain) && $https['ok']) {
            return 'https://'.$originIp;
        }

        if ($http['ok']) {
            return 'http://'.$originIp;
        }

        if ($https['ok']) {
            return 'https://'.$originIp;
        }

        return 'http://'.$originIp;
    }

    /**
     * @return array{ok: bool, status: int|null, location: string|null}
     */
    private function probeOriginEndpoint(string $originIp, string $hostHeader, string $scheme): array
    {
        $url = sprintf('%s://%s/', $scheme, $originIp);

        try {
            $request = Http::timeout(7)
                ->withoutRedirecting()
                ->withHeaders([
                    'Host' => $hostHeader,
                ]);

            if ($scheme === 'https') {
                $request = $request->withOptions(['verify' => false]);
            }

            $response = $request->get($url);
            $status = $response->status();
            $location = $response->header('Location');
            $ok = $response->successful() || in_array($status, [301, 302, 307, 308, 401, 403], true);

            return [
                'ok' => $ok,
                'status' => $status,
                'location' => is_string($location) ? $location : null,
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'status' => null,
                'location' => null,
            ];
        }
    }

    /**
     * @param  array{ok: bool, status: int|null, location: string|null}  $probe
     */
    private function isCanonicalHttpsRedirect(array $probe, string $domain): bool
    {
        if (! in_array((int) ($probe['status'] ?? 0), [301, 302, 307, 308], true)) {
            return false;
        }

        $location = trim((string) ($probe['location'] ?? ''));
        if ($location === '') {
            return false;
        }

        $parts = parse_url($location);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $scheme === 'https' && $host === strtolower($domain);
    }

    private function syncZoneOriginForExistingSite(Site $site): void
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return;
        }

        $zoneName = (string) data_get($site->provider_meta, 'zone_name', '');
        if ($zoneName === '') {
            $zoneName = (string) Str::of((string) ($site->cloudfront_domain_name ?? ''))
                ->before('.b-cdn.net')
                ->value();
        }

        if ($zoneName === '') {
            return;
        }

        $this->syncZoneOriginSettings(
            $zoneId,
            $zoneName,
            $this->resolvePreferredOriginUrl($site),
            strtolower((string) $site->apex_domain),
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function syncZoneOriginSettings(int $zoneId, string $zoneName, string $originUrl, string $originHostHeader, array $overrides = []): void
    {
        if ($zoneId <= 0 || $zoneName === '' || $originUrl === '' || $originHostHeader === '') {
            return;
        }

        try {
            $this->client()->post("/pullzone/{$zoneId}", $this->buildZoneUpdatePayload(
                zoneId: $zoneId,
                zoneName: $zoneName,
                originUrl: $originUrl,
                originHostHeader: $originHostHeader,
                overrides: $overrides,
            ))->throw();
        } catch (\Throwable) {
            // Non-fatal: keep runtime checks resilient and retry on later actions.
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildZoneUpdatePayload(
        int $zoneId,
        string $zoneName,
        string $originUrl,
        string $originHostHeader,
        array $overrides = [],
    ): array {
        return [
            'Id' => $zoneId,
            'Name' => $zoneName,
            'OriginUrl' => $originUrl,
            'OriginHostHeader' => $originHostHeader,
            'AddHostHeader' => true,
            'EnableAutoSSL' => true,
            'DisableCookies' => false,
            'EnableCookieVary' => true,
            'ErrorPageEnableCustomCode' => true,
            'ErrorPageCustomCode' => $this->edgeErrorPages()->buildNativeUnavailableHtml(),
            'Type' => 0,
        ] + $this->loggingSettingsPayload() + $overrides;
    }

    public function syncEdgeErrorPages(Site $site): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return [
                'changed' => false,
                'status' => 'inactive',
                'message' => 'Bunny pull zone is missing.',
            ];
        }

        $scriptId = (int) ($this->edgeErrorPages()->syncSharedScript()['script_id'] ?? 0);
        if ($scriptId <= 0) {
            throw new \RuntimeException('Shared Bunny edge error script is not available.');
        }

        $zoneName = (string) data_get($site->provider_meta, 'zone_name', $this->zoneNameFor($site));
        $originUrl = (string) data_get($site->provider_meta, 'origin_url', $this->resolvePreferredOriginUrl($site));
        $originHostHeader = (string) data_get($site->provider_meta, 'origin_host_header', strtolower((string) $site->apex_domain));

        $this->client()->post("/pullzone/{$zoneId}", $this->buildZoneUpdatePayload(
            zoneId: $zoneId,
            zoneName: $zoneName,
            originUrl: $originUrl,
            originHostHeader: $originHostHeader,
            overrides: ['MiddlewareScriptId' => $scriptId],
        ))->throw();

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['zone_id'] = $zoneId;
        $meta['zone_name'] = $zoneName;
        $meta['origin_url'] = $originUrl;
        $meta['origin_host_header'] = $originHostHeader;
        $meta['edge_error_script_id'] = $scriptId;
        $meta['edge_error_script_status'] = 'active';
        $meta['edge_error_script_last_error'] = null;
        $meta['edge_error_script_synced_at'] = now()->toIso8601String();

        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'changed' => true,
            'status' => 'active',
            'script_id' => $scriptId,
            'message' => 'Branded edge error pages are attached to this Bunny zone.',
        ];
    }

    /**
     * @return array{
     *   EnableLogging: bool,
     *   LoggingSaveToStorage: bool,
     *   LoggingStorageZoneId: int,
     *   LogForwardingEnabled: bool,
     *   LogForwardingHostname: string|null,
     *   LogForwardingPort: int,
     *   LogForwardingToken: string|null,
     *   LogForwardingProtocol: int|null,
     *   LogForwardingFormat?: int|string|null
     * }
     */
    private function loggingSettingsPayload(): array
    {
        $setting = SystemSetting::query()->where('key', 'bunny')->first();
        $value = $setting?->value;

        $configuredStorageZoneId = (int) (
            (is_array($value) ? ($value['logging_storage_zone_id'] ?? null) : null)
            ?? config('edge.bunny.logging_storage_zone_id', 0)
        );

        $saveToStorage = $configuredStorageZoneId > 0;
        $logForwardingEnabled = (bool) (
            (is_array($value) ? ($value['log_forwarding_enabled'] ?? null) : null)
            ?? config('edge.bunny.log_forwarding_enabled', false)
        );
        $logForwardingHostname = (string) (
            (is_array($value) ? ($value['log_forwarding_hostname'] ?? null) : null)
            ?? config('edge.bunny.log_forwarding_hostname', '')
        );
        $logForwardingPort = (int) (
            (is_array($value) ? ($value['log_forwarding_port'] ?? null) : null)
            ?? config('edge.bunny.log_forwarding_port', 514)
        );
        $logForwardingToken = (string) (
            (is_array($value) ? ($value['log_forwarding_token'] ?? null) : null)
            ?? config('edge.bunny.log_forwarding_token', '')
        );
        $logForwardingProtocol = $this->normalizeForwardingProtocol(
            (is_array($value) ? ($value['log_forwarding_protocol'] ?? null) : null)
                ?? config('edge.bunny.log_forwarding_protocol')
        );
        $logForwardingFormat = (is_array($value) ? ($value['log_forwarding_format'] ?? null) : null)
            ?? config('edge.bunny.log_forwarding_format');

        $payload = [
            'EnableLogging' => true,
            'LoggingSaveToStorage' => $saveToStorage,
            'LoggingStorageZoneId' => $saveToStorage ? $configuredStorageZoneId : 0,
            'LogForwardingEnabled' => $logForwardingEnabled && $logForwardingHostname !== '' && $logForwardingPort > 0,
            'LogForwardingHostname' => $logForwardingHostname !== '' ? $logForwardingHostname : null,
            'LogForwardingPort' => $logForwardingPort > 0 ? $logForwardingPort : 514,
            'LogForwardingToken' => $logForwardingToken !== '' ? $logForwardingToken : null,
            'LogForwardingProtocol' => $logForwardingProtocol,
        ];

        if ($logForwardingFormat !== null && $logForwardingFormat !== '') {
            $payload['LogForwardingFormat'] = $logForwardingFormat;
        }

        return $payload;
    }

    private function normalizeForwardingProtocol(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'udp' => 0,
            'tcp' => 1,
            'tcp_encrypted', 'tcp-encrypted', 'tls', 'tcp_tls' => 2,
            'datadog' => 3,
            default => null,
        };
    }

    public function checkDns(Site $site): array
    {
        if (! data_get($site->provider_meta, 'shield_zone_id')) {
            try {
                $this->shieldAccess()->ensureShieldZone($site);
            } catch (\Throwable) {
                // Best effort: DNS checks must continue even if security zone setup is delayed.
            }
        }

        $this->requestCertificatesForSiteHostnames($site);
        $this->syncZoneOriginForExistingSite($site);

        $target = rtrim(strtolower((string) $site->cloudfront_domain_name), '.');
        if ($target === '') {
            return [
                'validated' => false,
                'required_dns_records' => $site->required_dns_records,
                'message' => 'Edge target is missing.',
            ];
        }

        $domains = [$site->apex_domain];
        if ($site->www_enabled && $site->www_domain) {
            $domains[] = $site->www_domain;
        }

        $allValid = true;
        $dns = $site->required_dns_records ?? [];
        $traffic = Arr::get($dns, 'traffic', []);
        $resolved = [];

        foreach ($domains as $domain) {
            $lookup = $this->resolveDns($domain);
            $resolved[$domain] = $lookup;
            $valid = $this->domainPointsToTarget($lookup, $target);
            $allValid = $allValid && $valid;

            foreach ($traffic as &$record) {
                if (($record['name'] ?? null) === $domain) {
                    $record['status'] = $valid ? 'verified' : 'pending';
                }
            }
            unset($record);
        }

        $dns['traffic'] = $traffic;

        $message = $allValid
            ? 'Traffic DNS is pointed to the edge target.'
            : 'Point your domain to the edge target and retry.';

        $ssl = $this->checkSsl($site);

        if (! $allValid && in_array(($ssl['status'] ?? ''), ['pending', 'active'], true)) {
            $allValid = true;
            $message = 'The edge detected your hostname. DNS is accepted and SSL is provisioning.';
        }

        if ($allValid && ($ssl['status'] ?? null) === 'active') {
            $message = 'Traffic DNS is pointed correctly and SSL is active.';
        } elseif ($allValid && ($ssl['status'] ?? null) === 'pending') {
            $message = 'Traffic DNS is pointed correctly and SSL is still provisioning.';
        }

        return [
            'validated' => $allValid,
            'ok' => $allValid,
            'resolved_targets' => $resolved,
            'expected_target' => $target,
            'required_dns_records' => $dns,
            'message' => $message,
        ];
    }

    public function checkSsl(Site $site): array
    {
        $this->requestCertificatesForSiteHostnames($site);
        $this->syncZoneOriginForExistingSite($site);

        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return ['status' => 'error', 'message' => 'Bunny pull zone is missing.'];
        }

        $response = $this->client()->get("/pullzone/{$zoneId}");

        if (! $response->successful()) {
            return ['status' => 'pending', 'message' => 'Unable to read Bunny SSL state yet.'];
        }

        $payload = $response->json();
        $hostnames = collect(Arr::get($payload, 'Hostnames', Arr::get($payload, 'hostnames', [])));

        if ($hostnames->isEmpty()) {
            return ['status' => 'pending', 'message' => 'Waiting for Bunny hostname certificate state.'];
        }

        $domains = collect([$site->apex_domain, $site->www_enabled ? $site->www_domain : null])
            ->filter()
            ->values();

        $tracked = $hostnames->filter(function ($row) use ($domains) {
            $value = strtolower((string) Arr::get($row, 'Value', Arr::get($row, 'value', Arr::get($row, 'Hostname', Arr::get($row, 'hostname', '')))));

            return $domains->contains($value);
        });

        if ($tracked->isEmpty()) {
            $tracked = $hostnames;
        }

        $trackedDetails = $tracked
            ->map(function ($row): array {
                $hostname = (string) Arr::get($row, 'Value', Arr::get($row, 'value', Arr::get($row, 'Hostname', Arr::get($row, 'hostname', ''))));
                $certificateStatus = strtolower((string) Arr::get($row, 'CertificateStatus', Arr::get($row, 'certificateStatus', 'pending')));
                $isValid = Arr::get($row, 'IsCertificateValid', Arr::get($row, 'isCertificateValid'));
                $hasCertificate = Arr::get($row, 'HasCertificate', Arr::get($row, 'hasCertificate'));

                $active = is_bool($isValid)
                    ? $isValid
                    : (is_bool($hasCertificate) && $hasCertificate === true
                        ? true
                        : in_array($certificateStatus, ['active', 'valid', 'issued', 'enabled'], true));

                return [
                    'hostname' => strtolower($hostname),
                    'certificate_status' => $certificateStatus !== '' ? $certificateStatus : 'pending',
                    'is_valid' => (bool) $active,
                    'has_certificate' => is_bool($hasCertificate) ? $hasCertificate : (bool) $active,
                    'force_ssl' => (bool) Arr::get($row, 'ForceSSL', Arr::get($row, 'forceSSL', false)),
                ];
            })
            ->values()
            ->all();

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['ssl'] = array_merge((array) ($meta['ssl'] ?? []), [
            'enable_auto_ssl' => (bool) Arr::get($payload, 'EnableAutoSSL', true),
            'enable_tls1' => (bool) Arr::get($payload, 'EnableTLS1', false),
            'enable_tls1_1' => (bool) Arr::get($payload, 'EnableTLS1_1', false),
            'verify_origin_ssl' => (bool) Arr::get($payload, 'VerifyOriginSSL', false),
            'force_ssl_enabled' => $trackedDetails !== [] && collect($trackedDetails)->every(fn (array $row): bool => (bool) ($row['force_ssl'] ?? false)),
            'force_ssl_hostnames' => collect($trackedDetails)
                ->map(fn (array $row): array => [
                    'hostname' => (string) ($row['hostname'] ?? ''),
                    'force_ssl' => (bool) ($row['force_ssl'] ?? false),
                ])
                ->values()
                ->all(),
            'hostnames' => $trackedDetails,
            'checked_at' => now()->toIso8601String(),
        ]);
        $site->forceFill(['provider_meta' => $meta])->save();

        $allActive = $tracked->every(function ($row) {
            $status = strtolower((string) Arr::get($row, 'CertificateStatus', Arr::get($row, 'certificateStatus', '')));
            $isValid = Arr::get($row, 'IsCertificateValid', Arr::get($row, 'isCertificateValid'));
            $hasCertificate = Arr::get($row, 'HasCertificate', Arr::get($row, 'hasCertificate'));

            if (is_bool($isValid)) {
                return $isValid;
            }

            if (is_bool($hasCertificate) && $hasCertificate === true) {
                return true;
            }

            return in_array($status, ['active', 'valid', 'issued', 'enabled'], true);
        });

        if ($allActive) {
            return ['status' => 'active', 'message' => 'Bunny SSL certificate is active.', 'hostnames' => $trackedDetails];
        }

        return ['status' => 'pending', 'message' => 'DNS is detected. Waiting for Bunny SSL certificate issuance.', 'hostnames' => $trackedDetails];
    }

    protected function requestCertificatesForSiteHostnames(Site $site): void
    {
        $hostnames = [$site->apex_domain];

        if ($site->www_enabled && $site->www_domain) {
            $hostnames[] = $site->www_domain;
        }

        foreach ($hostnames as $hostname) {
            $this->requestFreeCertificate($hostname);
        }
    }

    protected function requestFreeCertificate(string $hostname): void
    {
        if ($hostname === '') {
            return;
        }

        try {
            $this->client()
                ->withQueryParameters([
                    'hostname' => $hostname,
                    'useOnlyHttp01' => 'true',
                ])
                ->get('/pullzone/loadFreeCertificate');
        } catch (\Throwable) {
            // Keep onboarding resilient: cert requests are best effort and can be retried during checks.
        }
    }

    public function purgeCache(Site $site, array $paths = ['/*']): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return ['changed' => false, 'message' => 'Edge zone is not provisioned yet.'];
        }

        $paths = array_values($paths);

        if ($paths === [] || $paths === ['/*']) {
            $this->client()->post("/pullzone/{$zoneId}/purgeCache")->throw();

            return [
                'changed' => true,
                'paths' => ['/*'],
                'message' => 'Edge cache purge requested for all files.',
            ];
        }

        foreach ($paths as $path) {
            $this->client()
                ->withQueryParameters(['url' => $path])
                ->post("/pullzone/{$zoneId}/purgeCache")
                ->throw();
        }

        return [
            'changed' => true,
            'paths' => $paths,
            'message' => 'Edge cache purge requested for selected paths.',
        ];
    }

    public function setUnderAttackMode(Site $site, bool $enabled): array
    {
        return [
            'changed' => false,
            'enabled' => $enabled,
            'message' => 'Under-attack mode is not supported for this edge network yet.',
        ];
    }

    public function setDevelopmentMode(Site $site, bool $enabled): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return [
                'changed' => false,
                'enabled' => $enabled,
                'message' => 'Edge deployment is not provisioned yet.',
            ];
        }

        $zoneResponse = $this->client()->get("/pullzone/{$zoneId}");
        if (! $zoneResponse->successful()) {
            throw new \RuntimeException('Unable to load edge zone settings.');
        }

        $zone = $zoneResponse->json();
        $zoneName = (string) (Arr::get($zone, 'Name') ?: data_get($site->provider_meta, 'zone_name', $this->zoneNameFor($site)));
        $originUrl = (string) (Arr::get($zone, 'OriginUrl') ?: $this->resolvePreferredOriginUrl($site));
        $originHostHeader = (string) (Arr::get($zone, 'OriginHostHeader') ?: strtolower((string) $site->apex_domain));

        $payload = $this->buildZoneUpdatePayload(
            zoneId: $zoneId,
            zoneName: $zoneName,
            originUrl: $originUrl,
            originHostHeader: $originHostHeader,
            overrides: [
                // Development mode bypasses cache and optimization.
                'DisableCache' => $enabled,
                'EnableOptimizers' => ! $enabled,
                'EnableQueryStringOrdering' => ! $enabled,
            ],
        );

        $this->client()->post("/pullzone/{$zoneId}", $payload)->throw();

        return [
            'changed' => true,
            'enabled' => $enabled,
            'message' => $enabled
                ? 'Development mode enabled. Edge cache and optimization are disabled.'
                : 'Development mode disabled. Standard edge cache and optimization are enabled.',
        ];
    }

    public function applySiteControlSetting(Site $site, string $setting, mixed $value): array
    {
        $normalized = strtolower(trim($setting));
        $controls = $this->controlPanelState($site);

        return match ($normalized) {
            'cache_enabled' => $this->applyCacheControls($site, array_merge($controls, [
                'cache_enabled' => (bool) $value,
            ]), 'Cache setting updated.'),
            'cache_mode' => $this->applyCacheControls($site, array_merge($controls, [
                'cache_mode' => $this->normalizeCacheMode((string) $value),
            ]), 'Cache mode updated.'),
            'browser_cache_ttl' => $this->applyCacheControls($site, array_merge($controls, [
                'browser_cache_ttl' => $this->normalizeBrowserCacheTtl($value),
            ]), 'Browser cache policy updated.'),
            'query_string_policy' => $this->applyCacheControls($site, array_merge($controls, [
                'query_string_policy' => $this->normalizeQueryStringPolicy((string) $value),
            ]), 'Query string cache policy updated.'),
            'optimizer_minify_css' => $this->applyCacheControls($site, array_merge($controls, [
                'optimizer_minify_css' => (bool) $value,
            ]), 'CSS minification setting updated.'),
            'optimizer_minify_js' => $this->applyCacheControls($site, array_merge($controls, [
                'optimizer_minify_js' => (bool) $value,
            ]), 'JavaScript minification setting updated.'),
            'optimizer_images' => $this->applyCacheControls($site, array_merge($controls, [
                'optimizer_images' => (bool) $value,
            ]), 'Image optimization setting updated.'),
            'cache_exclusions' => $this->applyManagedCacheExclusions(
                $site,
                array_merge($controls, [
                    'cache_exclusions' => $this->normalizeCacheExclusions((array) $value),
                ]),
                'WordPress cache bypass rules updated.'
            ),
            'tls1_enabled' => $this->applySslControls($site, array_merge($controls, [
                'tls1_enabled' => (bool) $value,
            ]), 'TLS 1.0 support updated.'),
            'tls1_1_enabled' => $this->applySslControls($site, array_merge($controls, [
                'tls1_1_enabled' => (bool) $value,
            ]), 'TLS 1.1 support updated.'),
            'origin_ssl_verification' => $this->applySslControls($site, array_merge($controls, [
                'origin_ssl_verification' => (bool) $value,
            ]), 'Origin certificate verification updated.'),
            'origin_host_header' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_host_header' => $this->normalizeOriginHostHeader((string) $value, $site),
            ]), 'Origin host header updated.'),
            'request_coalescing_enabled' => $this->applyOriginControls($site, array_merge($controls, [
                'request_coalescing_enabled' => (bool) $value,
            ]), 'Request coalescing updated.'),
            'request_coalescing_timeout' => $this->applyOriginControls($site, array_merge($controls, [
                'request_coalescing_timeout' => $this->normalizeRequestCoalescingTimeout($value),
            ]), 'Request coalescing timeout updated.'),
            'origin_retries' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_retries' => $this->normalizeOriginRetries($value),
            ]), 'Origin retry count updated.'),
            'origin_connect_timeout' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_connect_timeout' => $this->normalizeOriginConnectTimeout($value),
            ]), 'Origin connect timeout updated.'),
            'origin_response_timeout' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_response_timeout' => $this->normalizeOriginResponseTimeout($value),
            ]), 'Origin response timeout updated.'),
            'origin_retry_delay' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_retry_delay' => $this->normalizeOriginRetryDelay($value),
            ]), 'Origin retry delay updated.'),
            'origin_retry_5xx' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_retry_5xx' => (bool) $value,
            ]), '5xx retry policy updated.'),
            'origin_retry_connection_timeout' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_retry_connection_timeout' => (bool) $value,
            ]), 'Connection timeout retry policy updated.'),
            'origin_retry_response_timeout' => $this->applyOriginControls($site, array_merge($controls, [
                'origin_retry_response_timeout' => (bool) $value,
            ]), 'Response timeout retry policy updated.'),
            'stale_while_updating' => $this->applyOriginControls($site, array_merge($controls, [
                'stale_while_updating' => (bool) $value,
            ]), 'Stale-while-updating policy updated.'),
            'stale_while_offline' => $this->applyOriginControls($site, array_merge($controls, [
                'stale_while_offline' => (bool) $value,
            ]), 'Stale-while-offline policy updated.'),
            'https_enforced' => $this->applyHttpsEnforcement($site, array_merge($controls, [
                'https_enforced' => (bool) $value,
            ]), 'HTTPS enforcement updated.'),
            'origin_lockdown', 'waf_preset' => $this->storeControlPanelOnly($site, array_merge($controls, [
                $normalized => $value,
            ]), "Control [{$normalized}] was saved, but this provider does not enforce it yet."),
            default => [
                'changed' => false,
                'message' => "Unsupported control [{$normalized}] for Bunny.",
                'setting' => $normalized,
                'value' => $value,
            ],
        };
    }

    public function deleteDeployment(Site $site): array
    {
        $customerEdgeAliasRecordId = (string) data_get($site->provider_meta, 'customer_edge_alias_record_id', '');
        $customerEdgeDomain = (string) data_get($site->provider_meta, 'customer_edge_domain', '');

        if ($customerEdgeAliasRecordId !== '' || $customerEdgeDomain !== '') {
            $this->cloudflareDns()->deleteEdgeAlias(
                $customerEdgeAliasRecordId !== '' ? $customerEdgeAliasRecordId : null,
                $customerEdgeDomain !== '' ? $customerEdgeDomain : null,
            );
        }

        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        $zoneIds = $this->collectRelatedZoneIds($site, $zoneId);
        $shieldZoneIds = $this->collectRelatedShieldZoneIds($site, $zoneIds);

        if ($zoneIds === [] && $shieldZoneIds === []) {
            return [
                'changed' => false,
                'message' => 'No edge deployment is linked to this site.',
            ];
        }

        $deleted = [];
        $failed = [];
        $downgradedShieldZones = [];

        foreach ($shieldZoneIds as $shieldZoneId) {
            $result = $this->shieldSecurity()->downgradePlan($site, $shieldZoneId);

            if ((bool) ($result['changed'] ?? false)) {
                $downgradedShieldZones[] = $shieldZoneId;
            }
        }

        foreach ($zoneIds as $candidateId) {
            $response = $this->client()->delete("/pullzone/{$candidateId}");

            if ($response->successful() || in_array($response->status(), [404, 410], true)) {
                $deleted[] = $candidateId;

                continue;
            }

            $failed[] = "{$candidateId}:{$response->status()}";
        }

        if ($failed !== []) {
            throw new \RuntimeException('Unable to delete edge deployment(s): '.implode(', ', $failed));
        }

        $remainingZoneIds = $this->collectRelatedZoneIds($site, 0);
        if ($remainingZoneIds !== []) {
            throw new \RuntimeException('Unable to verify Bunny pull zone cleanup. Remaining zone ids: '.implode(', ', $remainingZoneIds));
        }

        $verifiedShieldZoneIds = [];
        $remainingPremiumShieldZoneIds = [];

        foreach ($shieldZoneIds as $shieldZoneId) {
            $planState = $this->shieldSecurity()->currentPlanState($shieldZoneId);

            if (
                ! (bool) ($planState['exists'] ?? false)
                || (
                    ! (bool) ($planState['premium_plan'] ?? false)
                    && ! (bool) ($planState['whitelabel_response_pages'] ?? false)
                    && (int) ($planState['plan_type'] ?? 0) <= 0
                )
            ) {
                $verifiedShieldZoneIds[] = $shieldZoneId;

                continue;
            }

            $remainingPremiumShieldZoneIds[] = $shieldZoneId;
        }

        if ($shieldZoneIds !== [] && $remainingPremiumShieldZoneIds === []) {
            return [
                'changed' => $deleted !== [],
                'message' => sprintf('Edge deployment deleted (%d) and Bunny Shield downgrade verified.', count($deleted)),
                'deleted_zone_ids' => $deleted,
                'downgraded_shield_zone_ids' => $downgradedShieldZones,
                'verified_downgraded_shield_zone_ids' => $verifiedShieldZoneIds,
            ];
        }

        if ($remainingPremiumShieldZoneIds !== []) {
            throw new \RuntimeException('Unable to verify Bunny Shield plan downgrade. Remaining premium shield zone ids: '.implode(', ', $remainingPremiumShieldZoneIds));
        }

        return [
            'changed' => $deleted !== [],
            'message' => sprintf('Edge deployment deleted (%d).', count($deleted)),
            'deleted_zone_ids' => $deleted,
            'downgraded_shield_zone_ids' => $downgradedShieldZones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setTroubleshootingMode(Site $site, bool $enabled): array
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $snapshot = (array) data_get($meta, 'troubleshooting_snapshot', []);

        if ($enabled) {
            $snapshot = [
                'development_mode' => (bool) $site->development_mode,
                'waf_enabled' => (bool) data_get($this->shieldSecurity()->currentSettings($site), 'waf_enabled', true),
            ];

            $meta['troubleshooting_snapshot'] = $snapshot;
        }

        $targetDevelopmentMode = $enabled
            ? true
            : (bool) ($snapshot['development_mode'] ?? false);
        $targetWafEnabled = $enabled
            ? false
            : (bool) ($snapshot['waf_enabled'] ?? true);

        $development = $this->setDevelopmentMode($site, $targetDevelopmentMode);
        $shield = $this->shieldSecurity()->setTroubleshootingMode($site, $enabled, $targetWafEnabled);

        if (! $enabled) {
            unset($meta['troubleshooting_snapshot']);
        }

        $meta['troubleshooting_mode'] = $enabled;
        $meta['troubleshooting_mode_updated_at'] = now()->toIso8601String();
        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'changed' => (bool) ($development['changed'] ?? false) || (bool) ($shield['changed'] ?? false),
            'development_mode' => $targetDevelopmentMode,
            'waf_enabled' => $targetWafEnabled,
            'message' => $enabled
                ? 'Troubleshooting mode enabled. Bunny WAF and edge caching are relaxed for testing.'
                : 'Troubleshooting mode disabled. Bunny WAF and edge caching are restored.',
            'development_result' => $development,
            'shield_result' => $shield,
        ];
    }

    /**
     * @return array{
     *   cache_enabled:bool,
     *   cache_mode:string,
     *   https_enforced:bool,
     *   origin_lockdown:bool,
     *   waf_preset:string,
     *   cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>,
     *   browser_cache_ttl:int,
     *   query_string_policy:string,
     *   optimizer_minify_css:bool,
     *   optimizer_minify_js:bool,
     *   optimizer_images:bool,
     *   tls1_enabled:bool,
     *   tls1_1_enabled:bool,
     *   origin_ssl_verification:bool,
     *   origin_host_header:string,
     *   request_coalescing_enabled:bool,
     *   request_coalescing_timeout:int,
     *   origin_retries:int,
     *   origin_connect_timeout:int,
     *   origin_response_timeout:int,
     *   origin_retry_delay:int,
     *   origin_retry_5xx:bool,
     *   origin_retry_connection_timeout:bool,
     *   origin_retry_response_timeout:bool,
     *   stale_while_updating:bool,
     *   stale_while_offline:bool
     * }
     */
    protected function controlPanelState(Site $site): array
    {
        $required = is_array($site->required_dns_records) ? $site->required_dns_records : [];
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $defaultCacheExclusions = app(BunnyGlobalDefaultsService::class)->cacheExclusionsForSite($site);

        $controls = array_merge(
            [
                'cache_enabled' => true,
                'cache_mode' => 'standard',
                'https_enforced' => true,
                'origin_lockdown' => false,
                'waf_preset' => 'baseline',
                'cache_exclusions' => $defaultCacheExclusions,
                'browser_cache_ttl' => -1,
                'query_string_policy' => 'ignore',
                'optimizer_minify_css' => true,
                'optimizer_minify_js' => true,
                'optimizer_images' => true,
                'tls1_enabled' => false,
                'tls1_1_enabled' => false,
                'origin_ssl_verification' => false,
                'origin_host_header' => strtolower((string) $site->apex_domain),
                'request_coalescing_enabled' => true,
                'request_coalescing_timeout' => 30,
                'origin_retries' => 1,
                'origin_connect_timeout' => 10,
                'origin_response_timeout' => 60,
                'origin_retry_delay' => 1,
                'origin_retry_5xx' => true,
                'origin_retry_connection_timeout' => true,
                'origin_retry_response_timeout' => false,
                'stale_while_updating' => true,
                'stale_while_offline' => true,
            ],
            (array) data_get($required, 'control_panel', []),
            (array) data_get($meta, 'control_panel', [])
        );

        $controls['cache_enabled'] = (bool) ($controls['cache_enabled'] ?? true);
        $controls['cache_mode'] = $this->normalizeCacheMode((string) ($controls['cache_mode'] ?? 'standard'));
        $controls['https_enforced'] = (bool) ($controls['https_enforced'] ?? true);
        $controls['origin_lockdown'] = (bool) ($controls['origin_lockdown'] ?? false);
        $controls['waf_preset'] = in_array((string) ($controls['waf_preset'] ?? 'baseline'), ['baseline', 'strict'], true)
            ? (string) $controls['waf_preset']
            : 'baseline';
        $controls['cache_exclusions'] = $this->normalizeCacheExclusions((array) ($controls['cache_exclusions'] ?? $defaultCacheExclusions));
        $controls['browser_cache_ttl'] = $this->normalizeBrowserCacheTtl($controls['browser_cache_ttl'] ?? -1);
        $controls['query_string_policy'] = $this->normalizeQueryStringPolicy((string) ($controls['query_string_policy'] ?? 'ignore'));
        $controls['optimizer_minify_css'] = (bool) ($controls['optimizer_minify_css'] ?? true);
        $controls['optimizer_minify_js'] = (bool) ($controls['optimizer_minify_js'] ?? true);
        $controls['optimizer_images'] = (bool) ($controls['optimizer_images'] ?? true);
        $controls['tls1_enabled'] = (bool) ($controls['tls1_enabled'] ?? false);
        $controls['tls1_1_enabled'] = (bool) ($controls['tls1_1_enabled'] ?? false);
        $controls['origin_ssl_verification'] = (bool) ($controls['origin_ssl_verification'] ?? false);
        $controls['origin_host_header'] = $this->normalizeOriginHostHeader((string) ($controls['origin_host_header'] ?? strtolower((string) $site->apex_domain)), $site);
        $controls['request_coalescing_enabled'] = (bool) ($controls['request_coalescing_enabled'] ?? true);
        $controls['request_coalescing_timeout'] = $this->normalizeRequestCoalescingTimeout($controls['request_coalescing_timeout'] ?? 30);
        $controls['origin_retries'] = $this->normalizeOriginRetries($controls['origin_retries'] ?? 1);
        $controls['origin_connect_timeout'] = $this->normalizeOriginConnectTimeout($controls['origin_connect_timeout'] ?? 10);
        $controls['origin_response_timeout'] = $this->normalizeOriginResponseTimeout($controls['origin_response_timeout'] ?? 60);
        $controls['origin_retry_delay'] = $this->normalizeOriginRetryDelay($controls['origin_retry_delay'] ?? 1);
        $controls['origin_retry_5xx'] = (bool) ($controls['origin_retry_5xx'] ?? true);
        $controls['origin_retry_connection_timeout'] = (bool) ($controls['origin_retry_connection_timeout'] ?? true);
        $controls['origin_retry_response_timeout'] = (bool) ($controls['origin_retry_response_timeout'] ?? false);
        $controls['stale_while_updating'] = (bool) ($controls['stale_while_updating'] ?? true);
        $controls['stale_while_offline'] = (bool) ($controls['stale_while_offline'] ?? true);

        return $controls;
    }

    protected function normalizeCacheMode(string $mode): string
    {
        return strtolower(trim($mode)) === 'aggressive' ? 'aggressive' : 'standard';
    }

    protected function normalizeBrowserCacheTtl(mixed $ttl): int
    {
        $value = (int) $ttl;

        return in_array($value, [-1, 0, 300, 3600, 14400, 86400, 604800], true) ? $value : -1;
    }

    protected function normalizeQueryStringPolicy(string $policy): string
    {
        return match (strtolower(trim($policy))) {
            'include', 'ignore' => strtolower(trim($policy)),
            default => 'ignore',
        };
    }

    protected function normalizeOriginHostHeader(string $host, Site $site): string
    {
        $normalized = strtolower(trim($host));

        if ($normalized === '') {
            return $this->preferredOriginHostHeader($site);
        }

        return preg_replace('/[^a-z0-9.-]/', '', $normalized) ?: $this->preferredOriginHostHeader($site);
    }

    protected function preferredOriginHostHeader(Site $site): string
    {
        if ($site->www_enabled && filled($site->www_domain)) {
            return strtolower((string) $site->www_domain);
        }

        return strtolower((string) $site->apex_domain);
    }

    protected function normalizeRequestCoalescingTimeout(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [5, 10, 15, 30, 60], true) ? $value : 30;
    }

    protected function normalizeOriginRetries(mixed $value): int
    {
        $value = (int) $value;

        return max(0, min(5, $value));
    }

    protected function normalizeOriginConnectTimeout(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [5, 10, 15, 30, 60], true) ? $value : 10;
    }

    protected function normalizeOriginResponseTimeout(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [15, 30, 60, 120, 300], true) ? $value : 60;
    }

    protected function normalizeOriginRetryDelay(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [0, 1, 2, 5, 10], true) ? $value : 1;
    }

    /**
     * @param  array{
     *   cache_enabled:bool,
     *   cache_mode:string,
     *   https_enforced:bool,
     *   origin_lockdown:bool,
     *   waf_preset:string,
     *   cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>,
     *   browser_cache_ttl:int,
     *   query_string_policy:string,
     *   optimizer_minify_css:bool,
     *   optimizer_minify_js:bool,
     *   optimizer_images:bool,
     *   tls1_enabled:bool,
     *   tls1_1_enabled:bool,
     *   origin_ssl_verification:bool
     * }  $controls
     * @return array<string,mixed>
     */
    protected function applySslControls(Site $site, array $controls, string $message): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return $this->storeControlPanelOnly($site, $controls, 'SSL settings were saved. They will apply after Bunny provisioning finishes.');
        }

        $zone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();
        $zoneName = (string) (Arr::get($zone, 'Name') ?: data_get($site->provider_meta, 'zone_name', $this->zoneNameFor($site)));
        $originUrl = (string) (Arr::get($zone, 'OriginUrl') ?: data_get($site->provider_meta, 'origin_url', $this->resolvePreferredOriginUrl($site)));
        $originHostHeader = (string) (Arr::get($zone, 'OriginHostHeader') ?: data_get($site->provider_meta, 'origin_host_header', strtolower((string) $site->apex_domain)));

        $this->client()->post("/pullzone/{$zoneId}", $this->buildZoneUpdatePayload(
            zoneId: $zoneId,
            zoneName: $zoneName,
            originUrl: $originUrl,
            originHostHeader: $originHostHeader,
            overrides: [
                'EnableTLS1' => (bool) ($controls['tls1_enabled'] ?? false),
                'EnableTLS1_1' => (bool) ($controls['tls1_1_enabled'] ?? false),
                'VerifyOriginSSL' => (bool) ($controls['origin_ssl_verification'] ?? false),
            ],
        ))->throw();

        $freshZone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();

        return $this->persistControlPanelState($site, $controls, [
            'ssl' => [
                'enable_auto_ssl' => (bool) Arr::get($freshZone, 'EnableAutoSSL', true),
                'enable_tls1' => (bool) Arr::get($freshZone, 'EnableTLS1', false),
                'enable_tls1_1' => (bool) Arr::get($freshZone, 'EnableTLS1_1', false),
                'verify_origin_ssl' => (bool) Arr::get($freshZone, 'VerifyOriginSSL', false),
                'force_ssl_enabled' => $this->allTrackedHostnamesForceSsl($site, $freshZone),
                'force_ssl_hostnames' => $this->trackedHostnameForceSslStates($site, $freshZone),
            ],
            'zone_settings' => [
                'EnableAutoSSL' => (bool) Arr::get($freshZone, 'EnableAutoSSL', true),
                'EnableTLS1' => (bool) Arr::get($freshZone, 'EnableTLS1', false),
                'EnableTLS1_1' => (bool) Arr::get($freshZone, 'EnableTLS1_1', false),
                'VerifyOriginSSL' => (bool) Arr::get($freshZone, 'VerifyOriginSSL', false),
            ],
        ], $message);
    }

    /**
     * @param  array{
     *   cache_enabled:bool,
     *   cache_mode:string,
     *   https_enforced:bool,
     *   origin_lockdown:bool,
     *   waf_preset:string,
     *   cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>,
     *   browser_cache_ttl:int,
     *   query_string_policy:string,
     *   optimizer_minify_css:bool,
     *   optimizer_minify_js:bool,
     *   optimizer_images:bool,
     *   tls1_enabled:bool,
     *   tls1_1_enabled:bool,
     *   origin_ssl_verification:bool
     * }  $controls
     * @return array<string,mixed>
     */
    protected function applyHttpsEnforcement(Site $site, array $controls, string $message): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return $this->storeControlPanelOnly($site, $controls, 'HTTPS enforcement was saved. It will apply after edge provisioning finishes.');
        }

        $targetEnabled = (bool) ($controls['https_enforced'] ?? true);

        foreach ($this->trackedHostnamesForSite($site) as $hostname) {
            $this->client()->post("/pullzone/{$zoneId}/setForceSSL", [
                'Hostname' => $hostname,
                'ForceSSL' => $targetEnabled,
            ])->throw();
        }

        $freshZone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();

        return $this->persistControlPanelState($site, $controls, [
            'ssl' => [
                'force_ssl_enabled' => $this->allTrackedHostnamesForceSsl($site, $freshZone),
                'force_ssl_hostnames' => $this->trackedHostnameForceSslStates($site, $freshZone),
            ],
            'zone_settings' => [
                'ForceSSL' => $this->allTrackedHostnamesForceSsl($site, $freshZone),
            ],
        ], $message);
    }

    /**
     * @param  array{
     *   cache_enabled:bool,
     *   cache_mode:string,
     *   https_enforced:bool,
     *   origin_lockdown:bool,
     *   waf_preset:string,
     *   cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>,
     *   browser_cache_ttl:int,
     *   query_string_policy:string,
     *   optimizer_minify_css:bool,
     *   optimizer_minify_js:bool,
     *   optimizer_images:bool,
     *   tls1_enabled:bool,
     *   tls1_1_enabled:bool,
     *   origin_ssl_verification:bool,
     *   origin_host_header:string,
     *   request_coalescing_enabled:bool,
     *   request_coalescing_timeout:int,
     *   origin_retries:int,
     *   origin_connect_timeout:int,
     *   origin_response_timeout:int,
     *   origin_retry_delay:int,
     *   origin_retry_5xx:bool,
     *   origin_retry_connection_timeout:bool,
     *   origin_retry_response_timeout:bool,
     *   stale_while_updating:bool,
     *   stale_while_offline:bool
     * }  $controls
     * @return array<string,mixed>
     */
    protected function applyOriginControls(Site $site, array $controls, string $message): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return $this->storeControlPanelOnly($site, $controls, 'Origin settings were saved. They will apply after Bunny provisioning finishes.');
        }

        $zone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();
        $zoneName = (string) (Arr::get($zone, 'Name') ?: data_get($site->provider_meta, 'zone_name', $this->zoneNameFor($site)));
        $originUrl = (string) (Arr::get($zone, 'OriginUrl') ?: data_get($site->provider_meta, 'origin_url', $this->resolvePreferredOriginUrl($site)));
        $originHostHeader = $this->normalizeOriginHostHeader(
            (string) ($controls['origin_host_header'] ?? Arr::get($zone, 'OriginHostHeader', data_get($site->provider_meta, 'origin_host_header', strtolower((string) $site->apex_domain)))),
            $site,
        );

        $this->client()->post("/pullzone/{$zoneId}", $this->buildZoneUpdatePayload(
            zoneId: $zoneId,
            zoneName: $zoneName,
            originUrl: $originUrl,
            originHostHeader: $originHostHeader,
            overrides: [
                'OriginHostHeader' => $originHostHeader,
                'EnableRequestCoalescing' => (bool) ($controls['request_coalescing_enabled'] ?? true),
                'RequestCoalescingTimeout' => (int) ($controls['request_coalescing_timeout'] ?? 30),
                'OriginRetries' => (int) ($controls['origin_retries'] ?? 1),
                'OriginConnectTimeout' => (int) ($controls['origin_connect_timeout'] ?? 10),
                'OriginResponseTimeout' => (int) ($controls['origin_response_timeout'] ?? 60),
                'OriginRetryDelay' => (int) ($controls['origin_retry_delay'] ?? 1),
                'OriginRetry5XXResponses' => (bool) ($controls['origin_retry_5xx'] ?? true),
                'OriginRetryConnectionTimeout' => (bool) ($controls['origin_retry_connection_timeout'] ?? true),
                'OriginRetryResponseTimeout' => (bool) ($controls['origin_retry_response_timeout'] ?? false),
                'UseStaleWhileUpdating' => (bool) ($controls['stale_while_updating'] ?? true),
                'UseStaleWhileOffline' => (bool) ($controls['stale_while_offline'] ?? true),
            ],
        ))->throw();

        $freshZone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();

        return $this->persistControlPanelState($site, $controls, [
            'origin_url' => $originUrl,
            'origin_host_header' => (string) Arr::get($freshZone, 'OriginHostHeader', $originHostHeader),
            'origin' => [
                'host_header' => (string) Arr::get($freshZone, 'OriginHostHeader', $originHostHeader),
                'request_coalescing_enabled' => (bool) Arr::get($freshZone, 'EnableRequestCoalescing', true),
                'request_coalescing_timeout' => (int) Arr::get($freshZone, 'RequestCoalescingTimeout', 30),
                'origin_retries' => (int) Arr::get($freshZone, 'OriginRetries', 1),
                'origin_connect_timeout' => (int) Arr::get($freshZone, 'OriginConnectTimeout', 10),
                'origin_response_timeout' => (int) Arr::get($freshZone, 'OriginResponseTimeout', 60),
                'origin_retry_delay' => (int) Arr::get($freshZone, 'OriginRetryDelay', 1),
                'origin_retry_5xx' => (bool) Arr::get($freshZone, 'OriginRetry5XXResponses', true),
                'origin_retry_connection_timeout' => (bool) Arr::get($freshZone, 'OriginRetryConnectionTimeout', true),
                'origin_retry_response_timeout' => (bool) Arr::get($freshZone, 'OriginRetryResponseTimeout', false),
                'stale_while_updating' => (bool) Arr::get($freshZone, 'UseStaleWhileUpdating', true),
                'stale_while_offline' => (bool) Arr::get($freshZone, 'UseStaleWhileOffline', true),
            ],
            'zone_settings' => [
                'OriginHostHeader' => (string) Arr::get($freshZone, 'OriginHostHeader', $originHostHeader),
                'EnableRequestCoalescing' => (bool) Arr::get($freshZone, 'EnableRequestCoalescing', true),
                'RequestCoalescingTimeout' => (int) Arr::get($freshZone, 'RequestCoalescingTimeout', 30),
                'OriginRetries' => (int) Arr::get($freshZone, 'OriginRetries', 1),
                'OriginConnectTimeout' => (int) Arr::get($freshZone, 'OriginConnectTimeout', 10),
                'OriginResponseTimeout' => (int) Arr::get($freshZone, 'OriginResponseTimeout', 60),
                'OriginRetryDelay' => (int) Arr::get($freshZone, 'OriginRetryDelay', 1),
                'OriginRetry5XXResponses' => (bool) Arr::get($freshZone, 'OriginRetry5XXResponses', true),
                'OriginRetryConnectionTimeout' => (bool) Arr::get($freshZone, 'OriginRetryConnectionTimeout', true),
                'OriginRetryResponseTimeout' => (bool) Arr::get($freshZone, 'OriginRetryResponseTimeout', false),
                'UseStaleWhileUpdating' => (bool) Arr::get($freshZone, 'UseStaleWhileUpdating', true),
                'UseStaleWhileOffline' => (bool) Arr::get($freshZone, 'UseStaleWhileOffline', true),
            ],
        ], $message);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array{path_pattern:string,reason:string,enabled:bool}>
     */
    protected function normalizeCacheExclusions(array $rules): array
    {
        return collect($rules)
            ->map(function (array $row): array {
                return [
                    'path_pattern' => trim((string) ($row['path_pattern'] ?? '')),
                    'reason' => trim((string) ($row['reason'] ?? '')),
                    'enabled' => (bool) ($row['enabled'] ?? true),
                ];
            })
            ->filter(fn (array $row): bool => $row['path_pattern'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function trackedHostnamesForSite(Site $site): array
    {
        return collect([
            strtolower((string) $site->apex_domain),
            strtolower((string) ($site->www_enabled ? $site->www_domain : '')),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $zone
     * @return list<array{hostname:string,force_ssl:bool}>
     */
    protected function trackedHostnameForceSslStates(Site $site, array $zone): array
    {
        $tracked = $this->trackedHostnamesForSite($site);

        return collect((array) Arr::get($zone, 'Hostnames', []))
            ->map(function (array $row): array {
                return [
                    'hostname' => strtolower((string) Arr::get($row, 'Value', Arr::get($row, 'Hostname', ''))),
                    'force_ssl' => (bool) Arr::get($row, 'ForceSSL', false),
                ];
            })
            ->filter(fn (array $row): bool => $row['hostname'] !== '' && in_array($row['hostname'], $tracked, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $zone
     */
    protected function allTrackedHostnamesForceSsl(Site $site, array $zone): bool
    {
        $states = $this->trackedHostnameForceSslStates($site, $zone);
        $tracked = $this->trackedHostnamesForSite($site);

        if ($tracked === [] || $states === [] || count($states) < count($tracked)) {
            return false;
        }

        return collect($states)->every(fn (array $row): bool => (bool) ($row['force_ssl'] ?? false));
    }

    /**
     * @param  array<string,mixed>  $zone
     * @return array<int, array{path_pattern:string,reason:string,enabled:bool}>
     */
    protected function managedCacheExclusionsFromZone(array $zone): array
    {
        return collect((array) Arr::get($zone, 'EdgeRules', []))
            ->filter(fn (array $rule): bool => $this->isManagedCacheExclusionRule($rule))
            ->map(function (array $rule): array {
                $pattern = (string) Arr::get($rule, 'Triggers.0.PatternMatches.0', '');
                $description = trim((string) Arr::get($rule, 'Description', ''));
                $reason = trim(Str::after($description, '[FP_CACHE_EXCLUSION]'));

                return [
                    'path_pattern' => $pattern,
                    'reason' => $reason !== '' ? $reason : $pattern,
                    'enabled' => (bool) Arr::get($rule, 'Enabled', true),
                ];
            })
            ->filter(fn (array $rule): bool => $rule['path_pattern'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *   cache_enabled:bool,
     *   cache_mode:string,
     *   https_enforced:bool,
     *   origin_lockdown:bool,
     *   waf_preset:string,
     *   cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>,
     *   browser_cache_ttl:int,
     *   query_string_policy:string,
     *   optimizer_minify_css:bool,
     *   optimizer_minify_js:bool,
     *   optimizer_images:bool
     * }  $controls
     * @return array<string,mixed>
     */
    protected function applyCacheControls(Site $site, array $controls, string $message): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return $this->storeControlPanelOnly($site, $controls, 'Cache settings were saved. They will apply after Bunny provisioning finishes.');
        }

        $zoneResponse = $this->client()->get("/pullzone/{$zoneId}");
        if (! $zoneResponse->successful()) {
            throw new \RuntimeException('Unable to load Bunny pull zone settings.');
        }

        $zone = (array) $zoneResponse->json();
        $zoneName = (string) (Arr::get($zone, 'Name') ?: data_get($site->provider_meta, 'zone_name', $this->zoneNameFor($site)));
        $originUrl = (string) (Arr::get($zone, 'OriginUrl') ?: data_get($site->provider_meta, 'origin_url', $this->resolvePreferredOriginUrl($site)));
        $originHostHeader = (string) (Arr::get($zone, 'OriginHostHeader') ?: data_get($site->provider_meta, 'origin_host_header', strtolower((string) $site->apex_domain)));

        $cacheEnabled = (bool) ($controls['cache_enabled'] ?? true);
        $cacheMode = $this->normalizeCacheMode((string) ($controls['cache_mode'] ?? 'standard'));
        $effectiveDevelopmentMode = (bool) $site->development_mode;

        $disableCache = $effectiveDevelopmentMode ? true : ! $cacheEnabled;
        $enableOptimizers = $effectiveDevelopmentMode ? false : $cacheEnabled;
        $aggressive = $cacheEnabled && $cacheMode === 'aggressive' && ! $effectiveDevelopmentMode;
        $ignoreQueryStrings = ($controls['query_string_policy'] ?? 'ignore') === 'ignore';
        $browserCacheTtl = (int) ($controls['browser_cache_ttl'] ?? -1);
        $optimizerImages = $effectiveDevelopmentMode ? false : (bool) ($controls['optimizer_images'] ?? true);
        $optimizerMinifyCss = $effectiveDevelopmentMode ? false : (bool) ($controls['optimizer_minify_css'] ?? true);
        $optimizerMinifyJs = $effectiveDevelopmentMode ? false : (bool) ($controls['optimizer_minify_js'] ?? true);

        $overrides = [
            'DisableCache' => $disableCache,
            'EnableOptimizers' => $enableOptimizers,
            'EnableQueryStringOrdering' => $aggressive,
            'EnableSmartCache' => $aggressive,
            'IgnoreQueryStrings' => $ignoreQueryStrings,
            'CacheControlMaxAgeOverride' => -1,
            'CacheControlPublicMaxAgeOverride' => $browserCacheTtl,
            'OptimizerEnabled' => $optimizerImages,
            'OptimizerAutomaticOptimizationEnabled' => $optimizerImages,
            'OptimizerMinifyCSS' => $optimizerMinifyCss,
            'OptimizerMinifyJavaScript' => $optimizerMinifyJs,
        ];

        $this->client()->post("/pullzone/{$zoneId}", $this->buildZoneUpdatePayload(
            zoneId: $zoneId,
            zoneName: $zoneName,
            originUrl: $originUrl,
            originHostHeader: $originHostHeader,
            overrides: $overrides,
        ))->throw();

        $freshZone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();

        $finalMessage = $effectiveDevelopmentMode
            ? 'Cache settings were saved. Development mode is still overriding edge cache and optimization right now.'
            : $message;

        return $this->persistControlPanelState($site, $controls, [
            'zone_name' => $zoneName,
            'origin_url' => $originUrl,
            'origin_host_header' => $originHostHeader,
            'zone_settings' => [
                'DisableCache' => (bool) Arr::get($freshZone, 'DisableCache', $disableCache),
                'EnableOptimizers' => (bool) Arr::get($freshZone, 'EnableOptimizers', $enableOptimizers),
                'EnableQueryStringOrdering' => (bool) Arr::get($freshZone, 'EnableQueryStringOrdering', $aggressive),
                'EnableSmartCache' => (bool) Arr::get($freshZone, 'EnableSmartCache', $aggressive),
                'IgnoreQueryStrings' => (bool) Arr::get($freshZone, 'IgnoreQueryStrings', $ignoreQueryStrings),
                'CacheControlMaxAgeOverride' => (int) Arr::get($freshZone, 'CacheControlMaxAgeOverride', -1),
                'CacheControlPublicMaxAgeOverride' => (int) Arr::get($freshZone, 'CacheControlPublicMaxAgeOverride', $browserCacheTtl),
                'OptimizerEnabled' => (bool) Arr::get($freshZone, 'OptimizerEnabled', $optimizerImages),
                'OptimizerAutomaticOptimizationEnabled' => (bool) Arr::get($freshZone, 'OptimizerAutomaticOptimizationEnabled', $optimizerImages),
                'OptimizerMinifyCSS' => (bool) Arr::get($freshZone, 'OptimizerMinifyCSS', $optimizerMinifyCss),
                'OptimizerMinifyJavaScript' => (bool) Arr::get($freshZone, 'OptimizerMinifyJavaScript', $optimizerMinifyJs),
            ],
        ], $finalMessage);
    }

    /**
     * @param  array{cache_enabled:bool,cache_mode:string,https_enforced:bool,origin_lockdown:bool,waf_preset:string,cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>}  $controls
     * @return array<string,mixed>
     */
    protected function applyManagedCacheExclusions(Site $site, array $controls, string $message): array
    {
        $sync = $this->syncManagedCacheExclusions($site, $controls['cache_exclusions'] ?? []);

        return $this->persistControlPanelState($site, $controls, [
            'cache_exclusion_rules' => [
                'count' => (int) ($sync['count'] ?? 0),
                'rules' => (array) ($sync['rules'] ?? []),
            ],
        ], $message, (bool) ($sync['changed'] ?? true));
    }

    /**
     * @param  array<int, array{path_pattern:string,reason:string,enabled:bool}>|null  $rules
     * @return array{changed:bool,count:int,rules:array<int,array<string,mixed>>}
     */
    protected function syncManagedCacheExclusions(Site $site, ?array $rules = null): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));

        if ($zoneId <= 0) {
            return [
                'changed' => false,
                'count' => 0,
                'rules' => [],
            ];
        }

        $desiredRules = $this->normalizeCacheExclusions($rules ?? $this->controlPanelState($site)['cache_exclusions']);
        $zone = (array) $this->client()->get("/pullzone/{$zoneId}")->throw()->json();
        $existingRules = collect((array) Arr::get($zone, 'EdgeRules', []));
        $managedRules = $existingRules
            ->filter(fn (array $row): bool => $this->isManagedCacheExclusionRule($row))
            ->values();

        foreach ($managedRules as $row) {
            $ruleId = (string) ($row['Guid'] ?? $row['Id'] ?? '');

            if ($ruleId !== '') {
                $this->client()->delete("/pullzone/{$zoneId}/edgerules/{$ruleId}")->throw();
            }
        }

        $created = [];
        $orderIndex = 0;

        foreach (collect($desiredRules)->where('enabled', true)->values() as $rule) {
            $payload = $this->buildCacheExclusionEdgeRulePayload($rule, $orderIndex++);

            $this->client()
                ->post("/pullzone/{$zoneId}/edgerules/addOrUpdate", $payload)
                ->throw();

            $created[] = [
                'path_pattern' => (string) $rule['path_pattern'],
                'reason' => (string) $rule['reason'],
                'enabled' => true,
            ];
        }

        return [
            'changed' => true,
            'count' => count($created),
            'rules' => $created,
        ];
    }

    protected function isManagedCacheExclusionRule(array $rule): bool
    {
        $description = (string) ($rule['Description'] ?? '');

        return Str::startsWith($description, '[FP_CACHE_EXCLUSION]');
    }

    /**
     * @param  array{path_pattern:string,reason:string,enabled:bool}  $rule
     * @return array<string,mixed>
     */
    protected function buildCacheExclusionEdgeRulePayload(array $rule, int $orderIndex): array
    {
        $description = trim('[FP_CACHE_EXCLUSION] '.($rule['reason'] !== '' ? $rule['reason'] : $rule['path_pattern']));

        return [
            'Guid' => null,
            'ActionType' => 3,
            'ActionParameter1' => '0',
            'ActionParameter2' => null,
            'ActionParameter3' => null,
            'Triggers' => [[
                'Type' => 0,
                'PatternMatches' => [(string) $rule['path_pattern']],
                'PatternMatchingType' => 0,
                'Parameter1' => null,
            ]],
            'ExtraActions' => [[
                'ActionType' => 12,
                'ActionParameter1' => null,
                'ActionParameter2' => null,
                'ActionParameter3' => null,
            ]],
            'TriggerMatchingType' => 0,
            'Description' => $description,
            'Enabled' => true,
            'OrderIndex' => $orderIndex,
        ];
    }

    /**
     * @param  array{cache_enabled:bool,cache_mode:string,https_enforced:bool,origin_lockdown:bool,waf_preset:string,cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>}  $controls
     * @return array<string,mixed>
     */
    protected function storeControlPanelOnly(Site $site, array $controls, string $message): array
    {
        return $this->persistControlPanelState($site, $controls, [], $message, false);
    }

    /**
     * @param  array{cache_enabled:bool,cache_mode:string,https_enforced:bool,origin_lockdown:bool,waf_preset:string,cache_exclusions:array<int, array{path_pattern:string,reason:string,enabled:bool}>}  $controls
     * @param  array<string,mixed>  $providerMetaUpdates
     * @return array<string,mixed>
     */
    protected function persistControlPanelState(
        Site $site,
        array $controls,
        array $providerMetaUpdates,
        string $message,
        bool $changed = true,
    ): array {
        $required = is_array($site->required_dns_records) ? $site->required_dns_records : [];
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];

        data_set($required, 'control_panel', array_merge((array) data_get($required, 'control_panel', []), $controls));
        $meta = array_merge($meta, $providerMetaUpdates);
        $meta['control_panel'] = array_merge((array) data_get($meta, 'control_panel', []), $controls);

        $site->forceFill([
            'required_dns_records' => $required,
            'provider_meta' => $meta,
        ])->save();

        return [
            'changed' => $changed,
            'message' => $message,
            'control_panel' => data_get($required, 'control_panel', []),
            'provider_meta' => $providerMetaUpdates,
        ];
    }

    /**
     * @return list<int>
     */
    protected function collectRelatedZoneIds(Site $site, int $linkedZoneId = 0): array
    {
        $ids = [];

        if ($linkedZoneId > 0) {
            $ids[] = $linkedZoneId;
        }

        $zones = $this->listPullZones();
        if ($zones === []) {
            return array_values(array_unique($ids));
        }

        $expectedNames = array_filter([
            strtolower((string) data_get($site->provider_meta, 'zone_name', '')),
            strtolower($this->zoneNameFor($site)),
        ]);

        $domains = array_filter([
            strtolower((string) $site->apex_domain),
            strtolower((string) ($site->www_enabled ? $site->www_domain : '')),
        ]);

        foreach ($zones as $zone) {
            $candidateId = (int) (Arr::get($zone, 'Id') ?? Arr::get($zone, 'id') ?? 0);
            if ($candidateId <= 0) {
                continue;
            }

            $candidateName = strtolower((string) (Arr::get($zone, 'Name') ?? Arr::get($zone, 'name') ?? ''));
            $hostnames = collect((array) (Arr::get($zone, 'Hostnames') ?? Arr::get($zone, 'hostnames', [])))
                ->map(function ($row): string {
                    return strtolower((string) Arr::get($row, 'Value', Arr::get($row, 'value', Arr::get($row, 'Hostname', Arr::get($row, 'hostname', '')))));
                })
                ->filter()
                ->values()
                ->all();

            $matchesName = $candidateName !== '' && in_array($candidateName, $expectedNames, true);
            $matchesDomain = $domains !== [] && count(array_intersect($domains, $hostnames)) > 0;

            if ($matchesName || $matchesDomain) {
                $ids[] = $candidateId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listPullZones(): array
    {
        try {
            $response = $this->client()->get('/pullzone');

            if (! $response->successful()) {
                return [];
            }

            $payload = $response->json();

            if (is_array($payload) && array_is_list($payload)) {
                return $payload;
            }

            if (is_array($payload)) {
                $items = Arr::get($payload, 'Items', Arr::get($payload, 'items', []));

                return is_array($items) ? $items : [];
            }

            return [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $pullZoneIds
     * @return list<int>
     */
    protected function collectRelatedShieldZoneIds(Site $site, array $pullZoneIds = []): array
    {
        $storedIds = [];

        $storedShieldZoneId = (int) data_get($site->provider_meta, 'shield_zone_id', 0);
        if ($storedShieldZoneId > 0) {
            $storedIds[] = $storedShieldZoneId;
        }

        $zones = $this->listShieldZones();
        if ($zones === null) {
            return array_values(array_unique($storedIds));
        }

        $ids = [];

        foreach ($zones as $zone) {
            $candidateId = (int) (Arr::get($zone, 'Id')
                ?? Arr::get($zone, 'id')
                ?? Arr::get($zone, 'shieldZoneId')
                ?? Arr::get($zone, 'shield_zone_id')
                ?? 0);

            if ($candidateId <= 0) {
                continue;
            }

            $linkedPullZoneId = (int) (Arr::get($zone, 'PullZoneId')
                ?? Arr::get($zone, 'pullZoneId')
                ?? Arr::get($zone, 'pull_zone_id')
                ?? 0);

            if (in_array($candidateId, $storedIds, true) || ($pullZoneIds !== [] && in_array($linkedPullZoneId, $pullZoneIds, true))) {
                $ids[] = $candidateId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    protected function listShieldZones(): ?array
    {
        try {
            $response = $this->client()->get('/shield/shield-zones', [
                'page' => 1,
                'perPage' => 200,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();

            if (is_array($payload) && array_is_list($payload)) {
                return $payload;
            }

            if (! is_array($payload)) {
                return [];
            }

            foreach (['Items', 'items', 'data'] as $key) {
                $items = Arr::get($payload, $key);

                if (is_array($items) && array_is_list($items)) {
                    return $items;
                }
            }

            return [];
        } catch (\Throwable) {
            return null;
        }
    }

    protected function client(): PendingRequest
    {
        $apiKey = $this->bunnyApiKey();

        if ($apiKey === '') {
            throw new \RuntimeException('Bunny API key is not configured in system settings.');
        }

        return Http::baseUrl(rtrim((string) config('edge.bunny.base_url', 'https://api.bunny.net'), '/'))
            ->acceptJson()
            ->withHeaders([
                'AccessKey' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(15);
    }

    protected function bunnyApiKey(): string
    {
        $setting = SystemSetting::query()->where('key', 'bunny')->first();
        $value = $setting?->value;

        if (is_array($value)) {
            return (string) ($value['api_key'] ?? '');
        }

        return '';
    }

    protected function zoneNameFor(Site $site): string
    {
        $base = Str::of($site->apex_domain)
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '-')
            ->trim('-')
            ->limit(35, '')
            ->value();

        return trim("fp-{$site->id}-{$base}", '-');
    }

    protected function zoneEdgeDomain(string $zoneName): string
    {
        return strtolower($zoneName).'.b-cdn.net';
    }

    protected function customerEdgeAliasHostname(Site $site): string
    {
        $base = (string) Str::of((string) $site->apex_domain)
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->limit(50, '')
            ->value();

        if ($base === '') {
            $base = 'site-'.$site->id;
        }

        $label = $base.'-edge';
        $hostname = $this->cloudflareDns()->edgeAliasHostname($label);

        $ownerId = Site::query()
            ->where('id', '!=', $site->id)
            ->where('cloudfront_domain_name', $hostname)
            ->value('id');

        if ($ownerId) {
            $hostname = $this->cloudflareDns()->edgeAliasHostname($base.'-'.$site->id.'-edge');
        }

        return $hostname;
    }

    protected function cloudflareDns(): CloudflareDnsService
    {
        return $this->cloudflareDns ??= app(CloudflareDnsService::class);
    }

    /**
     * @return array<int, array{purpose:string,type:string,name:string,value:string,ttl:string,status:string,notes:string}>
     */
    protected function trafficRecords(Site $site, string $target): array
    {
        $records = [[
            'purpose' => 'traffic',
            'type' => 'CNAME/ALIAS',
            'name' => $site->apex_domain,
            'value' => $target,
            'ttl' => 'Auto',
            'status' => 'pending',
            'notes' => 'Use ALIAS/ANAME/CNAME flattening for apex if your DNS provider supports it.',
        ]];

        if ($site->www_enabled && $site->www_domain) {
            $records[] = [
                'purpose' => 'traffic',
                'type' => 'CNAME',
                'name' => $site->www_domain,
                'value' => $target,
                'ttl' => 'Auto',
                'status' => 'pending',
                'notes' => 'Point www directly to Bunny edge hostname.',
            ];
        }

        return $records;
    }

    protected function domainPointsToTarget(array $resolved, string $target): bool
    {
        $target = rtrim(strtolower($target), '.');

        $cname = collect((array) ($resolved['cname'] ?? []))
            ->map(fn (string $value) => rtrim(strtolower($value), '.'))
            ->contains($target);

        if ($cname) {
            return true;
        }

        $targetIps = gethostbynamel($target) ?: [];
        $domainIps = array_values(array_unique(array_merge(
            (array) ($resolved['a'] ?? []),
            (array) ($resolved['aaaa'] ?? [])
        )));

        if ($domainIps === [] || $targetIps === []) {
            return false;
        }

        return count(array_intersect($domainIps, $targetIps)) > 0;
    }

    /**
     * @return array{cname: array<int, string>, a: array<int, string>, aaaa: array<int, string>}
     */
    protected function resolveDns(string $domain): array
    {
        return [
            'cname' => $this->lookupCname($domain),
            'a' => $this->lookupByType($domain, 'A'),
            'aaaa' => $this->lookupByType($domain, 'AAAA'),
        ];
    }

    /**
     * @return list<string>
     */
    protected function lookupCname(string $name): array
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return [];
        }

        $process = new Process(['dig', '+short', 'CNAME', $name]);
        $process->setTimeout(10);
        $process->run();

        $records = collect(explode("\n", trim($process->getOutput())))->filter()->values()->all();

        if ($records !== []) {
            return $records;
        }

        $fallback = dns_get_record($name, DNS_CNAME);
        if (! is_array($fallback)) {
            return [];
        }

        return collect($fallback)
            ->map(fn (array $record) => (string) ($record['target'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function lookupByType(string $name, string $type): array
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return [];
        }

        $process = new Process(['dig', '+short', strtoupper($type), $name]);
        $process->setTimeout(10);
        $process->run();

        $records = collect(explode("\n", trim($process->getOutput())))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        if ($records !== []) {
            return $records;
        }

        $dnsType = strtoupper($type) === 'AAAA' ? DNS_AAAA : DNS_A;
        $fallback = dns_get_record($name, $dnsType);
        if (! is_array($fallback)) {
            return [];
        }

        return collect($fallback)
            ->map(function (array $record) use ($type): string {
                return (string) ($type === 'AAAA' ? ($record['ipv6'] ?? '') : ($record['ip'] ?? ''));
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function shieldAccess(): BunnyShieldAccessListService
    {
        return $this->shieldAccess ??= app(BunnyShieldAccessListService::class);
    }

    protected function edgeErrorPages(): BunnyEdgeErrorPageService
    {
        return $this->edgeErrorPages ??= app(BunnyEdgeErrorPageService::class);
    }

    protected function shieldSecurity(): BunnyShieldSecurityService
    {
        return $this->shieldSecurity ??= app(BunnyShieldSecurityService::class);
    }

    protected function shieldWaf(): BunnyShieldWafService
    {
        return $this->shieldWaf ??= app(BunnyShieldWafService::class);
    }
}
