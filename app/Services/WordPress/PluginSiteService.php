<?php

namespace App\Services\WordPress;

use App\Models\AlertChannel;
use App\Models\EdgeRequestLog;
use App\Models\PluginConnectionToken;
use App\Models\PluginSiteConnection;
use App\Models\Site;
use App\Models\SiteFirewallRule;
use App\Services\ActivityFeedService;
use App\Services\BandwidthUsageService;
use App\Services\Billing\SiteBillingStateService;
use App\Services\Edge\EdgeProviderManager;
use App\Services\Firewall\FirewallAccessControlService;
use App\Services\Firewall\FirewallInsightsPresenter;
use App\Jobs\ToggleTroubleshootingModeJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PluginSiteService
{
    public function __construct(
        protected ActivityFeedService $activityFeed,
        protected BandwidthUsageService $bandwidthUsage,
        protected SiteBillingStateService $billingState,
        protected PluginAlertChannelService $alertChannels,
        protected FirewallAccessControlService $accessControl,
        protected EdgeProviderManager $providers,
    ) {}

    /**
     * @return array{token: string, expires_at: string}
     */
    public function issueConnectionToken(Site $site, ?int $userId = null, int $ttlMinutes = 15): array
    {
        $plainToken = 'fps_' . Str::random(48);
        $expiresAt = now()->addMinutes(max(5, $ttlMinutes));

        PluginConnectionToken::query()
            ->where('site_id', $site->id)
            ->whereNull('consumed_at')
            ->delete();

        PluginConnectionToken::query()->create([
            'site_id' => $site->id,
            'created_by' => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function connect(string $connectionToken, array $payload): array
    {
        $tokenHash = hash('sha256', $connectionToken);

        return DB::transaction(function () use ($tokenHash, $payload): array {
            $token = PluginConnectionToken::query()
                ->with('site.organization.subscriptions.plan')
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $token) {
                throw new RuntimeException('The connection token is invalid.');
            }

            if ($token->consumed_at) {
                throw new RuntimeException('This connection token has already been used.');
            }

            if ($token->expires_at?->isPast()) {
                throw new RuntimeException('This connection token has expired.');
            }

            $plainSiteToken = Str::random(64);
            $now = now();

            $connection = PluginSiteConnection::query()->updateOrCreate(
                ['site_id' => $token->site_id],
                [
                    'site_token_hash' => hash('sha256', $plainSiteToken),
                    'status' => 'connected',
                    'home_url' => (string) ($payload['home_url'] ?? ''),
                    'site_url' => (string) ($payload['site_url'] ?? ''),
                    'admin_email' => (string) ($payload['admin_email'] ?? ''),
                    'plugin_version' => (string) ($payload['plugin_version'] ?? ''),
                    'last_connected_at' => $now,
                    'last_seen_at' => $now,
                ],
            );

            $token->forceFill([
                'consumed_at' => $now,
            ])->save();

            return [
                'site_id' => (string) $connection->site_id,
                'site_token' => $plainSiteToken,
                'connection_status' => 'connected',
            ];
        });
    }

    public function authenticate(Request $request, int|string|null $siteId = null): PluginSiteConnection
    {
        $providedSiteId = (int) ($siteId ?: $request->input('site_id', $request->query('site_id')));
        $bearerToken = (string) $request->bearerToken();

        if ($providedSiteId < 1 || $bearerToken === '') {
            throw new RuntimeException('A valid connected site token is required.');
        }

        $connection = PluginSiteConnection::query()
            ->with('site.organization.subscriptions.plan', 'site.analyticsMetric')
            ->where('site_id', $providedSiteId)
            ->where('site_token_hash', hash('sha256', $bearerToken))
            ->first();

        if (! $connection) {
            throw new RuntimeException('Plugin authentication failed.');
        }

        $connection->forceFill([
            'last_seen_at' => now(),
        ])->save();

        return $connection;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function storeReport(PluginSiteConnection $connection, array $report): array
    {
        $previousReport = is_array($connection->last_report_payload) ? $connection->last_report_payload : [];

        $connection->forceFill([
            'last_report_payload' => $report,
            'last_reported_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $site = $connection->site()->with('organization.subscriptions.plan')->first();

        if ($site) {
            $access = $this->billingAccessSummaryForSite($site);
            $this->alertChannels->dispatchAlertsForReport($connection, $previousReport, $report, (bool) ($access['pro_enabled'] ?? false));
        }

        return [
            'site_id' => (string) $connection->site_id,
            'received_at' => optional($connection->last_reported_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function latestReportForSite(Site $site): array
    {
        $payload = $site->pluginConnection?->last_report_payload;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressHealthSummaryForSite(Site $site): array
    {
        $report = $this->latestReportForSite($site);
        $summary = data_get($report, 'health.summary', []);
        $updates = data_get($report, 'health.updates', []);
        $checksum = data_get($report, 'health.core_checksum', []);

        return [
            'good' => (int) ($summary['good'] ?? 0),
            'warning' => (int) ($summary['warning'] ?? 0),
            'critical' => (int) ($summary['critical'] ?? 0),
            'core_updates' => (int) ($updates['core_updates'] ?? 0),
            'plugin_updates' => (int) ($updates['plugin_updates'] ?? 0),
            'theme_updates' => (int) ($updates['theme_updates'] ?? 0),
            'inactive_plugins' => (int) ($updates['inactive_plugins'] ?? 0),
            'checksum_status' => (string) ($checksum['status'] ?? 'unknown'),
            'checksum_summary' => (string) ($checksum['summary'] ?? 'No core checksum report yet.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressScanSummaryForSite(Site $site): array
    {
        $scan = data_get($this->latestReportForSite($site), 'malware_scan', []);

        return [
            'status' => (string) ($scan['status'] ?? 'idle'),
            'scanned_files' => (int) ($scan['scanned_files'] ?? 0),
            'discovered_files' => (int) ($scan['discovered_files'] ?? 0),
            'suspicious_files' => (int) ($scan['suspicious_files'] ?? 0),
            'skipped_files' => (int) ($scan['skipped_files'] ?? 0),
            'findings' => is_array($scan['findings'] ?? null) ? $scan['findings'] : [],
            'updated_at' => (string) ($scan['updated_at'] ?? ''),
            'finished_at' => (string) ($scan['finished_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressSiteMetaForSite(Site $site): array
    {
        $report = $this->latestReportForSite($site);
        $siteMeta = data_get($report, 'site', []);

        return [
            'home_url' => (string) ($siteMeta['home_url'] ?? ''),
            'site_url' => (string) ($siteMeta['site_url'] ?? ''),
            'wp_version' => (string) ($siteMeta['wp_version'] ?? ''),
            'php_version' => (string) ($siteMeta['php_version'] ?? ''),
            'plugin_version' => (string) ($siteMeta['plugin_version'] ?? ''),
            'generated_at' => (string) data_get($report, 'generated_at', ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function wordpressMalwareFindingsForSite(Site $site): array
    {
        return collect((array) data_get($this->wordpressScanSummaryForSite($site), 'findings', []))
            ->map(function (mixed $finding): array {
                $payload = is_array($finding) ? $finding : [];

                return [
                    'file' => (string) data_get($payload, 'file', '--'),
                    'type' => ucfirst((string) data_get($payload, 'type', 'review')),
                    'confidence' => ucfirst((string) data_get($payload, 'confidence', 'n/a')),
                    'reasons' => implode(', ', array_filter((array) data_get($payload, 'reasons', []))) ?: 'No reasons provided',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function billingAccessSummaryForSite(Site $site): array
    {
        $summary = $this->billingState->summaryForSite($site);
        $status = (string) ($summary['status'] ?? 'not_set_up');
        $plan = $summary['plan'];
        $proEnabled = in_array($status, ['active', 'trialing'], true);

        return [
            'pro_enabled' => $proEnabled,
            'status' => $status,
            'plan_name' => $plan?->name,
            'message' => $proEnabled
                ? (($plan?->name ?? 'Current plan') . ' includes live WordPress firewall and performance telemetry.')
                : ($plan
                    ? "Finish billing for {$plan->name} to unlock live firewall and performance telemetry in WordPress."
                    : 'Choose a paid FirePhage plan to unlock live firewall and performance telemetry in WordPress.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusSummary(PluginSiteConnection $connection): array
    {
        $site = $connection->site;
        $site->loadMissing('organization.subscriptions.plan');
        $access = $this->billingAccessSummaryForSite($site);

        return [
            'connected' => true,
            'site' => [
                'id' => (string) $site->id,
                'domain' => (string) ($site->apex_domain ?: $site->name),
                'provider' => (string) $site->provider,
                'status' => (string) $site->status,
                'onboarding_status' => (string) $site->onboarding_status,
            ],
            'plugin' => [
                'status' => (string) $connection->status,
                'home_url' => (string) $connection->home_url,
                'site_url' => (string) $connection->site_url,
                'admin_email' => (string) $connection->admin_email,
                'plugin_version' => (string) $connection->plugin_version,
                'last_connected_at' => optional($connection->last_connected_at)->toIso8601String(),
                'last_seen_at' => optional($connection->last_seen_at)->toIso8601String(),
                'last_reported_at' => optional($connection->last_reported_at)->toIso8601String(),
            ],
            'billing' => [
                'pro_enabled' => (bool) $access['pro_enabled'],
                'status' => (string) $access['status'],
                'plan_name' => (string) ($access['plan_name'] ?? ''),
                'message' => (string) $access['message'],
            ],
            'capabilities' => [
                'report_upload' => true,
                'firewall_summary' => (bool) $access['pro_enabled'],
                'performance_summary' => (bool) $access['pro_enabled'],
                'live_wordpress_telemetry' => (bool) $access['pro_enabled'],
            ],
            'alert_channels' => $this->alertChannelSummary($site),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentFirewallEventsForSite(Site $site, int $limit = 10): array
    {
        return EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->latest('event_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (EdgeRequestLog $row): array => [
                'timestamp' => optional($row->event_at)->toIso8601String(),
                'action' => (string) ($row->action ?: 'ALLOW'),
                'path' => (string) ($row->path ?: '/'),
                'status_code' => (int) ($row->status_code ?? 0),
                'country' => strtoupper((string) ($row->country ?: 'n/a')),
                'ip' => (string) ($row->ip ?: '-'),
                'method' => strtoupper((string) ($row->method ?: 'GET')),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function firewallSummary(PluginSiteConnection $connection): array
    {
        $site = $connection->site;
        $site->loadMissing('analyticsMetric', 'organization.subscriptions.plan');
        $access = $this->billingAccessSummaryForSite($site);
        $insights = app(FirewallInsightsPresenter::class)->insights($site);

        $since = now()->subDay();
        $blockedActions = ['BLOCK', 'DENY', 'CHALLENGE'];
        $blockedCount = EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->where('event_at', '>=', $since)
            ->whereIn('action', $blockedActions)
            ->count();
        $challengeCount = EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->where('event_at', '>=', $since)
            ->where('action', 'CHALLENGE')
            ->count();
        $requestTotal = max(1, (int) ($site->analyticsMetric?->total_requests_24h ?? 0));
        $challengeRate = round(($challengeCount / $requestTotal) * 100, 2);
        $botPressure = round(($blockedCount / $requestTotal) * 100, 2);
        $insightTotal = (int) data_get($insights, 'summary.total', 0);
        $topCountries = collect((array) data_get($insights, 'top_countries', []))
            ->map(fn (array $row): array => [
                'country' => strtoupper((string) ($row['country'] ?? '')),
                'requests' => (int) ($row['requests'] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['country'] !== '')
            ->take(5)
            ->values()
            ->all();
        $topIps = collect((array) data_get($insights, 'top_ips', []))
            ->map(fn (array $row): array => [
                'ip' => (string) ($row['ip'] ?? ''),
                'country' => strtoupper((string) ($row['country'] ?? '')),
                'requests' => (int) ($row['requests'] ?? 0),
                'blocked' => (int) ($row['blocked'] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['ip'] !== '')
            ->take(5)
            ->values()
            ->all();

        return [
            'connected' => true,
            'pro_enabled' => $access['pro_enabled'],
            'message' => (string) $access['message'],
            'plan_name' => (string) ($access['plan_name'] ?? ''),
            'site' => [
                'id' => (string) $site->id,
                'domain' => (string) ($site->apex_domain ?: $site->name),
                'provider' => (string) $site->provider,
            ],
            'status' => [
                'label' => $site->under_attack ? 'Under Attack' : ($site->status === Site::STATUS_ACTIVE ? 'Protected' : ucfirst((string) $site->status)),
                'under_attack' => (bool) $site->under_attack,
                'development_mode' => (bool) $site->development_mode,
                'troubleshooting_mode' => (bool) $site->troubleshooting_mode,
                'waf_status' => (string) data_get($site->provider_meta, 'shield_status', $site->waf_web_acl_arn ? 'active' : 'pending'),
            ],
            'metrics' => [
                'requests_blocked' => $access['pro_enabled'] ? $blockedCount : 0,
                'total_requests' => $access['pro_enabled'] ? max($insightTotal, (int) ($site->analyticsMetric?->total_requests_24h ?? 0)) : 0,
                'challenge_rate' => $access['pro_enabled'] ? $challengeRate : 0,
                'bot_pressure' => $access['pro_enabled'] ? $botPressure : 0,
            ],
            'controls' => [
                'protection_mode' => $access['pro_enabled']
                    ? ($site->under_attack ? 'Under Attack' : 'Adaptive WAF')
                    : 'Upgrade required',
                'trusted_ips' => $access['pro_enabled']
                    ? implode(', ', (array) data_get($site->provider_meta, 'firewall_policy.allow_ips', []))
                    : '',
                'country_blocks' => $access['pro_enabled']
                    ? implode(', ', (array) data_get($site->provider_meta, 'firewall_policy.block_countries', []))
                    : '',
                'troubleshooting_mode' => $access['pro_enabled'] ? (bool) $site->troubleshooting_mode : false,
            ],
            'options' => [
                'countries' => $access['pro_enabled'] ? $this->accessControl->countryOptions($site) : [],
                'continents' => $access['pro_enabled'] ? $this->accessControl->continentOptions($site) : [],
            ],
            'activity' => $access['pro_enabled'] ? $this->recentFirewallEventsForSite($site, 10) : [],
            'feed' => $access['pro_enabled'] ? $this->activityFeed->forSite($site, 10) : [],
            'insights' => [
                'top_countries' => $access['pro_enabled'] ? $topCountries : [],
                'top_ips' => $access['pro_enabled'] ? $topIps : [],
            ],
            'rules' => $access['pro_enabled'] ? $this->pluginFirewallRules($site) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function performanceSummary(PluginSiteConnection $connection): array
    {
        $site = $connection->site;
        $site->loadMissing('analyticsMetric', 'organization.subscriptions.plan');
        $access = $this->billingAccessSummaryForSite($site);

        $metric = $site->analyticsMetric;
        $bandwidth = $this->bandwidthUsage->forSite($site);
        $cached = (int) ($metric?->cached_requests_24h ?? 0);
        $origin = (int) ($metric?->origin_requests_24h ?? 0);
        $total = max(1, $cached + $origin);
        $topPaths = EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->where('event_at', '>=', now()->subDays(7))
            ->where('status_code', '<', 400)
            ->selectRaw('path, count(*) as hits')
            ->groupBy('path')
            ->orderByDesc('hits')
            ->limit(3)
            ->get()
            ->map(fn (EdgeRequestLog $row): array => [
                'path' => (string) ($row->path ?: '/'),
                'hits' => (int) ($row->hits ?? 0),
            ])
            ->all();

        return [
            'connected' => true,
            'pro_enabled' => $access['pro_enabled'],
            'message' => (string) $access['message'],
            'plan_name' => (string) ($access['plan_name'] ?? ''),
            'site' => [
                'id' => (string) $site->id,
                'domain' => (string) ($site->apex_domain ?: $site->name),
            ],
            'summary' => [
                'edge_hostname' => (string) ($site->cloudfront_domain_name ?: data_get($site->provider_meta, 'edge_domain', '')),
                'edge_enabled' => $access['pro_enabled'] && $site->status === Site::STATUS_ACTIVE,
                'development_mode' => (bool) $site->development_mode,
                'troubleshooting_mode' => (bool) $site->troubleshooting_mode,
                'requests_24h' => $access['pro_enabled'] ? (int) ($metric?->total_requests_24h ?? 0) : 0,
                'cache_hit_ratio' => $access['pro_enabled'] ? round((float) ($metric?->cache_hit_ratio ?? 0), 2) : 0.0,
                'origin_offload_ratio' => $access['pro_enabled'] ? round(($cached / $total) * 100, 2) : 0.0,
                'bandwidth_usage_gb' => $access['pro_enabled'] ? (float) $bandwidth['usage_gb'] : 0.0,
                'bandwidth_limit_gb' => $access['pro_enabled'] ? (int) $bandwidth['included_gb'] : 0,
            ],
            'settings' => [
                'smart_image_optimization' => $access['pro_enabled'] && ! $site->development_mode,
                'edge_compression' => $access['pro_enabled'] && ! $site->development_mode,
                'troubleshooting_mode' => $access['pro_enabled'] ? (bool) $site->troubleshooting_mode : false,
            ],
            'cache_rules' => $access['pro_enabled']
                ? [
                    [
                        'path' => 'Global edge cache',
                        'behavior' => 'Default cached delivery for anonymous traffic',
                        'state' => $site->status === Site::STATUS_ACTIVE ? 'Active' : 'Pending',
                    ],
                    [
                        'path' => 'Development mode',
                        'behavior' => 'Bypass cache while origin changes are being tested',
                        'state' => $site->development_mode ? 'Enabled' : 'Disabled',
                    ],
                    [
                        'path' => 'Edge compression',
                        'behavior' => 'Compress cacheable responses at the FirePhage edge',
                        'state' => ! $site->development_mode ? 'Enabled' : 'Disabled',
                    ],
                    [
                        'path' => 'Image optimization',
                        'behavior' => 'Optimize eligible images before delivery from the edge',
                        'state' => ! $site->development_mode ? 'Enabled' : 'Disabled',
                    ],
                ]
                : [],
            'top_paths' => $access['pro_enabled'] ? $topPaths : [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createFirewallRule(PluginSiteConnection $connection, array $payload): array
    {
        $site = $connection->site()->with('organization.users')->firstOrFail();
        $this->ensurePluginProAccess($site);

        $ruleType = strtolower(trim((string) ($payload['rule_type'] ?? '')));
        $action = strtolower(trim((string) ($payload['action'] ?? SiteFirewallRule::ACTION_BLOCK)));
        $targets = collect((array) ($payload['targets'] ?? []))
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        if ($targets === [] && ! empty($payload['target'])) {
            $targets = [strtoupper(trim((string) $payload['target']))];
        }

        if (! in_array($ruleType, [SiteFirewallRule::TYPE_IP, SiteFirewallRule::TYPE_COUNTRY, SiteFirewallRule::TYPE_CONTINENT], true)) {
            throw new RuntimeException('Unsupported firewall rule type.');
        }
        if (! in_array($action, [SiteFirewallRule::ACTION_BLOCK, SiteFirewallRule::ACTION_ALLOW], true)) {
            throw new RuntimeException('Unsupported firewall action.');
        }

        if ($targets === []) {
            throw new RuntimeException('Select at least one target to block.');
        }

        $actorId = $this->pluginActorId($site);

        if ($ruleType === SiteFirewallRule::TYPE_IP) {
            $created = $this->accessControl->createRules(
                site: $site,
                actorId: $actorId,
                ruleType: SiteFirewallRule::TYPE_IP,
                targets: $targets,
                action: $action,
                mode: SiteFirewallRule::MODE_ENFORCED,
                note: 'Added from WordPress plugin.',
            );
        } else {
            $created = $this->accessControl->createRuleSet(
                site: $site,
                actorId: $actorId,
                ruleType: $ruleType,
                targets: $targets,
                action: $action,
                mode: SiteFirewallRule::MODE_ENFORCED,
                note: 'Added from WordPress plugin.',
            );
        }

        return [
            'changed' => $created !== [],
            'message' => $created !== []
                ? 'Firewall block rule created.'
                : 'No firewall rule was created.',
        ];
    }

    public function removeFirewallRule(PluginSiteConnection $connection, int $ruleId): array
    {
        $site = $connection->site()->with('organization.users')->firstOrFail();
        $this->ensurePluginProAccess($site);

        $rule = SiteFirewallRule::query()
            ->where('site_id', $site->id)
            ->find($ruleId);

        if (! $rule) {
            throw new RuntimeException('Firewall rule not found.');
        }

        $this->accessControl->removeRule($rule, $this->pluginActorId($site));

        return [
            'changed' => true,
            'message' => 'Firewall rule removed.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function purgePluginCache(PluginSiteConnection $connection, array $payload): array
    {
        $site = $connection->site;
        $this->ensurePluginProAccess($site);

        $paths = collect((array) ($payload['paths'] ?? ['/*']))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($paths === []) {
            $paths = ['/*'];
        }

        return $this->providers->forSite($site)->purgeCache($site, $paths);
    }

    /**
     * @return array<string, mixed>
     */
    public function setPluginTroubleshootingMode(PluginSiteConnection $connection, bool $enabled): array
    {
        $site = $connection->site;
        $this->ensurePluginProAccess($site);

        (new ToggleTroubleshootingModeJob($site->id, $enabled, $this->pluginActorId($site)))
            ->handle($this->providers);

        $site->refresh();

        return [
            'changed' => true,
            'enabled' => (bool) $site->troubleshooting_mode,
            'development_mode' => (bool) $site->development_mode,
            'message' => $site->troubleshooting_mode
                ? 'Troubleshooting mode enabled.'
                : 'Troubleshooting mode disabled.',
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    protected function alertChannelSummary(Site $site): array
    {
        $channels = AlertChannel::query()
            ->where('organization_id', $site->organization_id)
            ->where('site_id', $site->id)
            ->whereIn('type', ['slack', 'webhook'])
            ->get()
            ->keyBy('type');

        $summaries = [];

        foreach (['slack', 'webhook'] as $type) {
            $record = $channels->get($type);
            $config = $record && is_array($record->config) ? $record->config : [];

            $configured = false;

            if ($type === 'slack') {
                $configured = ((string) ($config['webhook_url'] ?? '')) !== '';
            } elseif ($type === 'webhook') {
                $configured = ((string) ($config['url'] ?? '')) !== '';
            }

            $summaries[$type] = [
                'enabled' => (bool) ($record?->is_active ?? false),
                'configured' => $configured,
            ];
        }

        return $summaries;
    }

    protected function ensurePluginProAccess(Site $site): void
    {
        $access = $this->billingAccessSummaryForSite($site);

        if (! ($access['pro_enabled'] ?? false)) {
            throw new RuntimeException((string) ($access['message'] ?? 'A paid FirePhage plan is required for this action.'));
        }
    }

    protected function pluginActorId(Site $site): ?int
    {
        return $site->organization()
            ->first()?->users()
            ->orderBy('users.id')
            ->value('users.id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pluginFirewallRules(Site $site): array
    {
        return SiteFirewallRule::query()
            ->where('site_id', $site->id)
            ->whereIn('status', [
                SiteFirewallRule::STATUS_ACTIVE,
                SiteFirewallRule::STATUS_PENDING,
                SiteFirewallRule::STATUS_REMOVED,
            ])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (SiteFirewallRule $rule): array => [
                'id' => (int) $rule->id,
                'type' => (string) $rule->rule_type,
                'target' => (string) $rule->target,
                'action' => (string) $rule->action,
                'status' => (string) $rule->status,
                'note' => (string) ($rule->note ?? ''),
            ])
            ->all();
    }
}
