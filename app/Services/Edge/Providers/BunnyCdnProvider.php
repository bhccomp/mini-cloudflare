<?php

namespace App\Services\Edge\Providers;

use App\Models\Site;
use App\Models\SystemSetting;
use App\Services\Bunny\BunnyEdgeErrorPageService;
use App\Services\Bunny\BunnyShieldAccessListService;
use App\Services\Bunny\BunnyShieldSecurityService;
use App\Services\Billing\OrganizationEntitlementService;
use App\Services\Edge\EdgeProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BunnyCdnProvider implements EdgeProviderInterface
{
    public function __construct(
        protected ?BunnyShieldAccessListService $shieldAccess = null,
        protected ?BunnyShieldSecurityService $shieldSecurity = null,
        protected ?BunnyEdgeErrorPageService $edgeErrorPages = null,
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
        $originHostHeader = strtolower((string) $site->apex_domain);
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

                if ((bool) ($planState['premium_plan'] ?? false)) {
                    $planResult = $this->shieldSecurity()->downgradePlan($site, $shieldZoneId);
                    $shieldPlanStatus = ((bool) ($planResult['changed'] ?? false)) ? 'downgraded' : 'unchanged';
                    $shieldPlanMessage = (string) ($planResult['message'] ?? 'Bunny Shield is using the basic plan for this site.');
                } else {
                    $shieldPlanStatus = 'basic';
                    $shieldPlanMessage = 'Bunny Shield is using the basic plan for this site.';
                }
            }
        } catch (\Throwable $e) {
            $shieldMessage = $e->getMessage();
            $shieldPlanStatus = 'failed';
            $shieldPlanMessage = $e->getMessage();
        }

        $edgeScriptStatus = 'inactive';
        $edgeScriptId = null;
        $edgeScriptMessage = 'Origin custom error pages are currently disabled.';

        $edgeDomain = $this->zoneEdgeDomain($zoneName);
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
            'traffic' => $this->trafficRecords($site, $edgeDomain),
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
                'edge_error_script_last_error' => $edgeScriptMessage,
                'edge_domain' => $edgeDomain,
                'origin_url' => $originUrl,
                'origin_host_header' => $originHostHeader,
                'logging_enabled' => (bool) ($logging['EnableLogging'] ?? false),
                'logging_save_to_storage' => (bool) ($logging['LoggingSaveToStorage'] ?? false),
                'logging_storage_zone_id' => (int) ($logging['LoggingStorageZoneId'] ?? 0),
                'hostnames' => $hostnameResults,
            ],
            'distribution_id' => (string) $zoneId,
            'distribution_domain_name' => $edgeDomain,
            'required_dns_records' => $requiredDnsRecords,
            'dns_records' => $requiredDnsRecords['traffic'],
            'notes' => array_values(array_filter([
                'Edge zone provisioned. Point DNS traffic records to the edge domain.',
                $resolvedShieldZoneId ? 'Security zone has been enabled for this site.' : null,
                $resolvedShieldZoneId ? null : 'Security zone setup is pending and will retry automatically.',
                $shieldPlanStatus === 'active' ? 'Bunny Shield advanced plan has been enabled for this site.' : null,
                $shieldPlanStatus === 'unchanged' ? $shieldPlanMessage : null,
                $shieldPlanStatus === 'failed' ? 'Bunny Shield advanced plan could not be enabled automatically yet.' : null,
                'Origin custom error pages are disabled for this site.',
            ])),
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
        $domain = strtolower((string) $site->apex_domain);

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
            ? 'Traffic DNS is pointed to Bunny edge target.'
            : 'Point your domain to the Bunny edge target and retry.';

        if (! $allValid) {
            $ssl = $this->checkSsl($site);

            if (in_array(($ssl['status'] ?? ''), ['pending', 'active'], true)) {
                $allValid = true;
                $message = 'Bunny detected your hostname. DNS is accepted and SSL is provisioning.';
            }
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
            return ['status' => 'active', 'message' => 'Bunny SSL certificate is active.'];
        }

        return ['status' => 'pending', 'message' => 'DNS is detected. Waiting for Bunny SSL certificate issuance.'];
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

    public function deleteDeployment(Site $site): array
    {
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

            if (! (bool) ($planState['exists'] ?? false) || ! (bool) ($planState['premium_plan'] ?? false)) {
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
}
