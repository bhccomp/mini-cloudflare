<?php

namespace App\Services\WordPress;

use App\Models\EdgeRequestLog;
use App\Models\OrganizationSubscription;
use App\Models\PluginConnectionToken;
use App\Models\PluginSiteConnection;
use App\Models\Site;
use App\Services\ActivityFeedService;
use App\Services\BandwidthUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PluginSiteService
{
    public function __construct(
        protected ActivityFeedService $activityFeed,
        protected BandwidthUsageService $bandwidthUsage,
    ) {}

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
        $connection->forceFill([
            'last_report_payload' => $report,
            'last_reported_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return [
            'site_id' => (string) $connection->site_id,
            'received_at' => optional($connection->last_reported_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function firewallSummary(PluginSiteConnection $connection): array
    {
        $site = $connection->site;
        $site->loadMissing('analyticsMetric', 'organization.subscriptions.plan');

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

        $recentActivity = EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->latest('event_at')
            ->limit(5)
            ->get()
            ->map(fn (EdgeRequestLog $row): array => [
                'timestamp' => optional($row->event_at)->toIso8601String(),
                'action' => (string) ($row->action ?: 'ALLOW'),
                'path' => (string) ($row->path ?: '/'),
                'status_code' => (int) ($row->status_code ?? 0),
                'country' => strtoupper((string) ($row->country ?: 'n/a')),
            ])
            ->all();

        return [
            'connected' => true,
            'pro_enabled' => $this->isProEnabled($site),
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
                'requests_blocked' => $blockedCount,
                'challenge_rate' => $challengeRate,
                'bot_pressure' => $botPressure,
            ],
            'controls' => [
                'protection_mode' => $site->under_attack ? 'Under Attack' : 'Adaptive WAF',
                'trusted_ips' => implode(', ', (array) data_get($site->provider_meta, 'firewall_policy.allow_ips', [])),
                'country_blocks' => implode(', ', (array) data_get($site->provider_meta, 'firewall_policy.block_countries', [])),
            ],
            'activity' => $recentActivity,
            'feed' => $this->activityFeed->forSite($site, 5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function performanceSummary(PluginSiteConnection $connection): array
    {
        $site = $connection->site;
        $site->loadMissing('analyticsMetric', 'organization.subscriptions.plan');

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
            'pro_enabled' => $this->isProEnabled($site),
            'site' => [
                'id' => (string) $site->id,
                'domain' => (string) ($site->apex_domain ?: $site->name),
            ],
            'summary' => [
                'edge_hostname' => (string) ($site->cloudfront_domain_name ?: data_get($site->provider_meta, 'edge_domain', '')),
                'development_mode' => (bool) $site->development_mode,
                'requests_24h' => (int) ($metric?->total_requests_24h ?? 0),
                'cache_hit_ratio' => round((float) ($metric?->cache_hit_ratio ?? 0), 2),
                'origin_offload_ratio' => round(($cached / $total) * 100, 2),
                'bandwidth_usage_gb' => (float) $bandwidth['usage_gb'],
                'bandwidth_limit_gb' => (int) $bandwidth['included_gb'],
            ],
            'settings' => [
                'smart_image_optimization' => ! $site->development_mode,
                'edge_compression' => ! $site->development_mode,
            ],
            'cache_rules' => [
                ['path' => '/cart', 'behavior' => 'Bypass cache', 'state' => 'Enabled'],
                ['path' => '/checkout', 'behavior' => 'Bypass cache', 'state' => 'Enabled'],
                ['path' => '/blog/*', 'behavior' => 'TTL 1 hour', 'state' => 'Enabled'],
            ],
            'top_paths' => $topPaths,
        ];
    }

    private function isProEnabled(Site $site): bool
    {
        /** @var OrganizationSubscription|null $subscription */
        $subscription = $site->organization?->subscriptions
            ?->first(fn (OrganizationSubscription $sub): bool => in_array((string) $sub->status, ['active', 'trialing'], true));

        return $subscription !== null;
    }
}
