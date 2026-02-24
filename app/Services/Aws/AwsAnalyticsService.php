<?php

namespace App\Services\Aws;

use App\Models\Site;
use App\Models\SiteAnalyticsMetric;
use Aws\CloudWatch\CloudWatchClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class AwsAnalyticsService
{
    public function __construct(protected ?CloudWatchClient $cloudWatch = null) {}

    public function syncSiteMetrics(Site $site): ?SiteAnalyticsMetric
    {
        if (! $site->cloudfront_distribution_id) {
            return null;
        }

        $payload = $this->dryRun()
            ? $this->dryRunPayload($site)
            : $this->livePayload($site);

        return SiteAnalyticsMetric::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                ...$payload,
                'captured_at' => now(),
            ]
        );
    }

    protected function livePayload(Site $site): array
    {
        $end = now()->utc();
        $start7d = $end->copy()->subDays(7);
        $start24h = $end->copy()->subDay();

        $trafficSeries = $this->queryCloudFrontSeries(
            $site->cloudfront_distribution_id,
            'Requests',
            'Sum',
            $start7d,
            $end
        );

        $cacheHitSeries = $this->queryCloudFrontSeries(
            $site->cloudfront_distribution_id,
            'CacheHitRate',
            'Average',
            $start7d,
            $end
        );

        $wafBlockedSeries = $this->queryWafSeries($site, 'BlockedRequests', $start7d, $end);
        $wafAllowedSeries = $this->queryWafSeries($site, 'AllowedRequests', $start7d, $end);

        $labels = $this->lastSevenDayLabels($start7d);
        $blockedTrend = $this->normalizeSeriesForLabels($labels, $wafBlockedSeries);
        $allowedTrend = $this->normalizeSeriesForLabels($labels, $wafAllowedSeries);
        $requestTrend = $this->normalizeSeriesForLabels($labels, $trafficSeries);

        $blocked24h = $this->sumSince($wafBlockedSeries, $start24h);
        $allowed24h = $this->sumSince($wafAllowedSeries, $start24h);
        $total24h = $this->sumSince($trafficSeries, $start24h);
        $cacheHitRatio = $this->averageSince($cacheHitSeries, $start24h);
        $cached24h = $cacheHitRatio === null || $total24h === null ? null : (int) round($total24h * ($cacheHitRatio / 100));
        $origin24h = $cached24h === null || $total24h === null ? null : max(0, $total24h - $cached24h);

        $regionalTraffic = $this->deriveRegionalTraffic($total24h);
        $regionalThreat = $this->deriveRegionalThreat($blocked24h);

        return [
            'blocked_requests_24h' => $blocked24h,
            'allowed_requests_24h' => $allowed24h,
            'total_requests_24h' => $total24h,
            'cache_hit_ratio' => $cacheHitRatio,
            'cached_requests_24h' => $cached24h,
            'origin_requests_24h' => $origin24h,
            'trend_labels' => $labels,
            'blocked_trend' => $blockedTrend,
            'allowed_trend' => $allowedTrend,
            'regional_traffic' => $regionalTraffic,
            'regional_threat' => $regionalThreat,
            'source' => [
                'cloudfront_requests_points' => count($requestTrend),
                'waf_blocked_points' => count($blockedTrend),
                'waf_allowed_points' => count($allowedTrend),
                'mode' => 'aws_live',
            ],
        ];
    }

    protected function dryRunPayload(Site $site): array
    {
        $seed = crc32($site->apex_domain.$site->id);
        $labels = collect(range(6, 0))
            ->map(fn (int $days) => now()->subDays($days)->format('D'))
            ->values()
            ->all();

        $blockedTrend = [];
        $allowedTrend = [];

        foreach (range(0, 6) as $i) {
            $blockedTrend[] = 12 + (($seed + ($i * 13)) % 18);
            $allowedTrend[] = 280 + (($seed + ($i * 29)) % 180);
        }

        $blocked24h = (int) last($blockedTrend);
        $allowed24h = (int) last($allowedTrend);
        $total24h = $blocked24h + $allowed24h;
        $cacheHitRatio = 60 + (($seed % 23) / 2);
        $cached24h = (int) round($total24h * ($cacheHitRatio / 100));
        $origin24h = max(0, $total24h - $cached24h);

        return [
            'blocked_requests_24h' => $blocked24h,
            'allowed_requests_24h' => $allowed24h,
            'total_requests_24h' => $total24h,
            'cache_hit_ratio' => round($cacheHitRatio, 2),
            'cached_requests_24h' => $cached24h,
            'origin_requests_24h' => $origin24h,
            'trend_labels' => $labels,
            'blocked_trend' => $blockedTrend,
            'allowed_trend' => $allowedTrend,
            'regional_traffic' => $this->deriveRegionalTraffic($total24h),
            'regional_threat' => $this->deriveRegionalThreat($blocked24h),
            'source' => [
                'mode' => 'dry_run',
            ],
        ];
    }

    protected function queryCloudFrontSeries(
        string $distributionId,
        string $metricName,
        string $stat,
        Carbon $start,
        Carbon $end
    ): array {
        $result = $this->cloudWatchClient()->getMetricData([
            'StartTime' => $start->toDateTimeString(),
            'EndTime' => $end->toDateTimeString(),
            'ScanBy' => 'TimestampAscending',
            'MetricDataQueries' => [[
                'Id' => 'm1',
                'ReturnData' => true,
                'MetricStat' => [
                    'Metric' => [
                        'Namespace' => 'AWS/CloudFront',
                        'MetricName' => $metricName,
                        'Dimensions' => [
                            ['Name' => 'DistributionId', 'Value' => $distributionId],
                            ['Name' => 'Region', 'Value' => 'Global'],
                        ],
                    ],
                    'Period' => 86400,
                    'Stat' => $stat,
                ],
            ]],
        ])->toArray();

        return $this->resultToSeries($result);
    }

    protected function queryWafSeries(Site $site, string $metricName, Carbon $start, Carbon $end): array
    {
        if (! $site->waf_web_acl_arn) {
            return [];
        }

        $webAcl = $this->wafWebAclName($site->waf_web_acl_arn);
        if ($webAcl === '') {
            return [];
        }

        $result = $this->cloudWatchClient()->getMetricData([
            'StartTime' => $start->toDateTimeString(),
            'EndTime' => $end->toDateTimeString(),
            'ScanBy' => 'TimestampAscending',
            'MetricDataQueries' => [[
                'Id' => 'm1',
                'ReturnData' => true,
                'MetricStat' => [
                    'Metric' => [
                        'Namespace' => 'AWS/WAFV2',
                        'MetricName' => $metricName,
                        'Dimensions' => [
                            ['Name' => 'WebACL', 'Value' => $webAcl],
                            ['Name' => 'Region', 'Value' => 'Global'],
                            ['Name' => 'Rule', 'Value' => 'ALL'],
                        ],
                    ],
                    'Period' => 86400,
                    'Stat' => 'Sum',
                ],
            ]],
        ])->toArray();

        return $this->resultToSeries($result);
    }

    protected function resultToSeries(array $result): array
    {
        $timestamps = Arr::get($result, 'MetricDataResults.0.Timestamps', []);
        $values = Arr::get($result, 'MetricDataResults.0.Values', []);

        if (! is_array($timestamps) || ! is_array($values)) {
            return [];
        }

        $rows = [];
        foreach ($timestamps as $index => $timestamp) {
            $value = $values[$index] ?? null;
            if ($value === null) {
                continue;
            }

            $rows[] = [
                'timestamp' => Carbon::parse((string) $timestamp)->utc(),
                'value' => (float) $value,
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['timestamp'] <=> $b['timestamp']);

        return $rows;
    }

    protected function lastSevenDayLabels(Carbon $start): array
    {
        return collect(range(0, 6))
            ->map(fn (int $offset) => $start->copy()->addDays($offset)->format('D'))
            ->values()
            ->all();
    }

    protected function normalizeSeriesForLabels(array $labels, array $series): array
    {
        $valuesByDay = collect($series)
            ->keyBy(fn (array $row) => $row['timestamp']->format('D'))
            ->map(fn (array $row) => (int) round($row['value']))
            ->all();

        return collect($labels)->map(fn (string $day) => (int) ($valuesByDay[$day] ?? 0))->all();
    }

    protected function sumSince(array $series, Carbon $start): ?int
    {
        $subset = collect($series)
            ->filter(fn (array $row) => $row['timestamp']->greaterThanOrEqualTo($start))
            ->sum(fn (array $row) => $row['value']);

        return $subset > 0 ? (int) round($subset) : null;
    }

    protected function averageSince(array $series, Carbon $start): ?float
    {
        $subset = collect($series)
            ->filter(fn (array $row) => $row['timestamp']->greaterThanOrEqualTo($start))
            ->pluck('value');

        if ($subset->isEmpty()) {
            return null;
        }

        return round((float) $subset->average(), 2);
    }

    protected function deriveRegionalTraffic(?int $total): array
    {
        $total ??= 0;

        if ($total <= 0) {
            return [
                'North America' => 0,
                'Europe' => 0,
                'Asia Pacific' => 0,
                'South America' => 0,
                'Other' => 0,
            ];
        }

        return [
            'North America' => (int) round($total * 0.42),
            'Europe' => (int) round($total * 0.29),
            'Asia Pacific' => (int) round($total * 0.2),
            'South America' => (int) round($total * 0.06),
            'Other' => (int) round($total * 0.03),
        ];
    }

    protected function deriveRegionalThreat(?int $blocked): array
    {
        $blocked ??= 0;

        return [
            'North America' => max(0, (int) round($blocked * 0.24)),
            'Europe' => max(0, (int) round($blocked * 0.31)),
            'Asia Pacific' => max(0, (int) round($blocked * 0.27)),
            'South America' => max(0, (int) round($blocked * 0.09)),
            'Other' => max(0, (int) round($blocked * 0.37)),
        ];
    }

    protected function wafWebAclName(string $arn): string
    {
        $parts = explode('/', $arn);

        return (string) ($parts[count($parts) - 2] ?? '');
    }

    protected function cloudWatchClient(): CloudWatchClient
    {
        return $this->cloudWatch ??= new CloudWatchClient([
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
