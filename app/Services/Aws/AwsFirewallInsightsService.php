<?php

namespace App\Services\Aws;

use App\Models\Site;
use Aws\WAFV2\WAFV2Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AwsFirewallInsightsService
{
    public function __construct(protected ?WAFV2Client $waf = null) {}

    public function getSiteInsights(Site $site): array
    {
        if (! $site->waf_web_acl_arn) {
            return $this->emptyInsights('WAF is not provisioned yet.');
        }

        return Cache::remember(
            $this->cacheKey($site->id),
            now()->addMinutes(2),
            fn (): array => $this->dryRun() ? $this->dryRunInsights($site) : $this->liveInsights($site)
        );
    }

    public function forgetSiteInsightsCache(int $siteId): void
    {
        Cache::forget($this->cacheKey($siteId));
    }

    protected function liveInsights(Site $site): array
    {
        $end = now()->utc();
        $start = $end->copy()->subHours(3);

        $result = $this->wafClient()->getSampledRequests([
            'WebAclArn' => $site->waf_web_acl_arn,
            'RuleMetricName' => 'ALL',
            'Scope' => 'CLOUDFRONT',
            'TimeWindow' => [
                'StartTime' => $start->toIso8601String(),
                'EndTime' => $end->toIso8601String(),
            ],
            'MaxItems' => 500,
        ])->toArray();

        $events = collect((array) Arr::get($result, 'SampledRequests', []))
            ->map(fn (array $item): array => [
                'timestamp' => Carbon::parse((string) Arr::get($item, 'Timestamp', now())),
                'action' => strtoupper((string) Arr::get($item, 'Action', 'ALLOW')),
                'ip' => (string) Arr::get($item, 'Request.ClientIP', '-'),
                'country' => strtoupper((string) Arr::get($item, 'Request.Country', '??')),
                'method' => strtoupper((string) (Arr::get($item, 'Request.HTTPMethod') ?: Arr::get($item, 'Request.Method', 'GET'))),
                'uri' => (string) Arr::get($item, 'Request.URI', '/'),
                'rule' => (string) (Arr::get($item, 'RuleNameWithinRuleGroup') ?: Arr::get($item, 'RuleName') ?: 'ALL'),
            ])
            ->sortByDesc('timestamp')
            ->values();

        return $this->buildInsights($events, 'aws_live');
    }

    protected function dryRunInsights(Site $site): array
    {
        $seed = crc32($site->apex_domain.'|'.$site->id);
        $countries = ['US', 'DE', 'GB', 'FR', 'NL', 'IN', 'BR', 'JP', 'CA', 'AU'];
        $actions = ['ALLOW', 'BLOCK', 'ALLOW', 'ALLOW', 'BLOCK', 'COUNT'];

        $events = collect(range(1, 80))->map(function (int $i) use ($seed, $countries, $actions): array {
            $country = $countries[($seed + $i) % count($countries)];
            $action = $actions[($seed + ($i * 3)) % count($actions)];
            $octet = (($seed + ($i * 11)) % 240) + 10;
            $uri = ['/login', '/wp-login.php', '/api/v1/auth', '/checkout', '/contact', '/'][($seed + ($i * 5)) % 6];

            return [
                'timestamp' => now()->subMinutes($i * 2),
                'action' => $action,
                'ip' => "198.51.100.{$octet}",
                'country' => $country,
                'method' => ($i % 5 === 0) ? 'POST' : 'GET',
                'uri' => $uri,
                'rule' => $action === 'BLOCK' ? 'RateLimit' : 'ManagedRulesCommon',
            ];
        })->values();

        return $this->buildInsights($events, 'dry_run');
    }

    protected function buildInsights(\Illuminate\Support\Collection $events, string $source): array
    {
        $total = $events->count();
        $blocked = $events->filter(fn (array $event): bool => in_array($event['action'], ['BLOCK', 'CHALLENGE', 'CAPTCHA'], true))->count();
        $allowed = $events->filter(fn (array $event): bool => $event['action'] === 'ALLOW')->count();
        $counted = $events->filter(fn (array $event): bool => $event['action'] === 'COUNT')->count();

        $topCountries = $events
            ->groupBy('country')
            ->map(fn ($group, string $country): array => ['country' => $country, 'requests' => $group->count()])
            ->sortByDesc('requests')
            ->take(10)
            ->values()
            ->all();

        $topIps = $events
            ->groupBy('ip')
            ->map(fn ($group, string $ip): array => [
                'ip' => $ip,
                'requests' => $group->count(),
                'blocked' => $group->whereIn('action', ['BLOCK', 'CHALLENGE', 'CAPTCHA'])->count(),
            ])
            ->sortByDesc('requests')
            ->take(10)
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => $total,
                'blocked' => $blocked,
                'allowed' => $allowed,
                'counted' => $counted,
                'block_ratio' => $total > 0 ? round(($blocked / $total) * 100, 2) : 0.0,
            ],
            'top_countries' => $topCountries,
            'top_ips' => $topIps,
            'events' => $events->take(30)->all(),
            'captured_at' => now(),
            'source' => $source,
            'message' => null,
        ];
    }

    protected function emptyInsights(string $message): array
    {
        return [
            'summary' => [
                'total' => 0,
                'blocked' => 0,
                'allowed' => 0,
                'counted' => 0,
                'block_ratio' => 0.0,
            ],
            'top_countries' => [],
            'top_ips' => [],
            'events' => [],
            'captured_at' => now(),
            'source' => 'none',
            'message' => $message,
        ];
    }

    protected function cacheKey(int $siteId): string
    {
        return 'firewall-insights:site:'.$siteId;
    }

    protected function wafClient(): WAFV2Client
    {
        return $this->waf ??= new WAFV2Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => config('services.aws_edge.access_key_id'),
                'secret' => config('services.aws_edge.secret_access_key'),
            ],
        ]);
    }

    protected function dryRun(): bool
    {
        return (bool) config('services.aws_edge.dry_run', true);
    }
}
