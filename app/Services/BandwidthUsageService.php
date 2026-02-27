<?php

namespace App\Services;

use App\Models\Site;

class BandwidthUsageService
{
    /**
     * @return array{
     *   usage_gb: float,
     *   included_gb: int,
     *   percent_used: float,
     *   warning: bool,
     *   plan_code: string
     * }
     */
    public function forSite(Site $site): array
    {
        $site->loadMissing(['analyticsMetric', 'organization.subscriptions.plan']);

        $usageGb = $this->resolveUsageGb($site);
        $planCode = $this->resolvePlanCode($site);
        $includedGb = $this->includedLimitForPlan($planCode);
        $percent = $includedGb > 0 ? round(($usageGb / $includedGb) * 100, 2) : 0.0;

        return [
            'usage_gb' => round($usageGb, 2),
            'included_gb' => $includedGb,
            'percent_used' => $percent,
            'warning' => $percent >= 80,
            'plan_code' => $planCode,
        ];
    }

    protected function resolveUsageGb(Site $site): float
    {
        $source = (array) ($site->analyticsMetric?->source ?? []);

        $monthlyGb = data_get($source, 'monthly_bandwidth_gb');
        if (is_numeric($monthlyGb)) {
            return (float) $monthlyGb;
        }

        $monthlyBytes = data_get($source, 'monthly_bandwidth_bytes');
        if (is_numeric($monthlyBytes)) {
            return ((float) $monthlyBytes) / 1073741824;
        }

        $dailyRequests = (float) ($site->analyticsMetric?->total_requests_24h ?? 0);
        $estimatedDailyMb = $dailyRequests * 0.34;

        return ($estimatedDailyMb * 30) / 1024;
    }

    protected function resolvePlanCode(Site $site): string
    {
        $subscription = $site->organization?->subscriptions
            ?->first(fn ($sub): bool => in_array((string) $sub->status, ['active', 'trialing'], true))
            ?? $site->organization?->subscriptions?->first();

        $code = strtolower((string) ($subscription?->plan?->code ?? 'default'));

        return $code !== '' ? $code : 'default';
    }

    protected function includedLimitForPlan(string $planCode): int
    {
        $limits = (array) config('ui.bandwidth_limits', []);

        $default = (int) ($limits['default'] ?? 500);

        return (int) ($limits[$planCode] ?? $default);
    }
}
