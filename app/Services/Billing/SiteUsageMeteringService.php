<?php

namespace App\Services\Billing;

use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Stripe\StripeClient;

class SiteUsageMeteringService
{
    public function __construct(
        private readonly SubscriptionSiteAssignmentService $assignmentService,
        private readonly BillingNotificationService $notifications,
    ) {}

    public function currentMonthSummary(Site $site, ?Plan $plan = null, ?OrganizationSubscription $subscription = null): array
    {
        $plan ??= $subscription?->plan ?? $this->selectedPlanForSite($site);
        $subscription ??= $this->subscriptionForSite($site);

        $siteIds = $subscription
            ? $this->assignmentService->coveredSiteIds($subscription)
            : [$site->id];

        if ($siteIds === []) {
            $siteIds = [$site->id];
        }

        $requests = \App\Models\EdgeRequestLog::query()
            ->whereIn('site_id', $siteIds)
            ->whereBetween('event_at', [$this->periodStart(), $this->periodEnd()])
            ->count();

        $included = (int) ($plan?->included_requests_per_month ?? 0);
        $overageRequests = max(0, $requests - $included);
        $blockSize = max(1, (int) ($plan?->overage_block_size ?? 1000));
        $overageBlocks = $overageRequests > 0 ? (int) ceil($overageRequests / $blockSize) : 0;
        $estimatedOverageCents = $overageBlocks * (int) ($plan?->overage_price_cents ?? 0);

        return [
            'requests' => $requests,
            'included_requests' => $included,
            'overage_requests' => $overageRequests,
            'overage_blocks' => $overageBlocks,
            'overage_block_size' => $blockSize,
            'estimated_overage_cents' => $estimatedOverageCents,
            'period_start' => $this->periodStart(),
            'period_end' => $this->periodEnd(),
            'covered_site_ids' => $siteIds,
            'covered_sites_count' => count($siteIds),
            'last_reported_overage_requests' => (int) data_get($subscription?->meta, 'last_reported_overage_requests', 0),
        ];
    }

    public function syncCurrentMonthOverageToStripe(Site $site): array
    {
        $subscription = $this->subscriptionForSite($site);
        $plan = $subscription?->plan;

        if (! $subscription || ! $plan || ! $plan->stripe_request_meter_id || ! $plan->hasRequestOverageBilling()) {
            return ['reported' => false, 'reason' => 'missing_subscription_or_meter'];
        }

        if (! in_array((string) $subscription->status, ['active', 'trialing', 'past_due'], true)) {
            return ['reported' => false, 'reason' => 'inactive_subscription'];
        }

        $customerId = (string) ($subscription->stripe_customer_id ?: $site->organization?->stripe_customer_id);
        if ($customerId === '') {
            return ['reported' => false, 'reason' => 'missing_customer'];
        }

        $summary = $this->currentMonthSummary($site, $plan, $subscription);
        $billingMonth = $this->periodStart()->format('Y-m');
        $lastReportedMonth = (string) data_get($subscription->meta, 'last_reported_billing_month', '');
        $alreadyReported = $lastReportedMonth === $billingMonth
            ? (int) data_get($subscription->meta, 'last_reported_overage_requests', 0)
            : 0;

        $delta = max(0, (int) $summary['overage_requests'] - $alreadyReported);
        $this->notifyUsageThresholds($subscription->fresh(['plan', 'organization', 'sites']), $summary, $billingMonth);

        if ($delta <= 0) {
            return ['reported' => false, 'reason' => 'no_new_overage', 'summary' => $summary];
        }

        $this->stripe()->billing->meterEvents->create([
            'event_name' => $this->requestMeterEventName($plan),
            'identifier' => sprintf('subscription-%d-%s-%d', $subscription->id, $billingMonth, $summary['overage_requests']),
            'payload' => [
                'stripe_customer_id' => $customerId,
                'value' => (string) $delta,
            ],
            'timestamp' => now()->timestamp,
        ]);

        $subscription->forceFill([
            'meta' => array_merge($subscription->meta ?? [], [
                'last_reported_billing_month' => $billingMonth,
                'last_reported_overage_requests' => (int) $summary['overage_requests'],
                'last_usage_sync_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return ['reported' => true, 'summary' => $summary, 'delta' => $delta];
    }

    public function notifyUsageThresholds(OrganizationSubscription $subscription, array $summary, string $billingMonth): void
    {
        $included = (int) ($summary['included_requests'] ?? 0);

        if ($included < 1) {
            return;
        }

        $requests = (int) ($summary['requests'] ?? 0);
        $thresholds = [];

        if ($requests >= $included) {
            $thresholds[] = 100;
        } elseif ($requests >= (int) ceil($included * 0.8)) {
            $thresholds[] = 80;
        }

        if ($thresholds === []) {
            return;
        }

        $meta = $subscription->meta ?? [];

        foreach ($thresholds as $threshold) {
            $metaKey = sprintf('usage_threshold_%d_month', $threshold);

            if ((string) data_get($meta, $metaKey, '') === $billingMonth) {
                continue;
            }

            $this->notifications->sendUsageThreshold($subscription, $threshold, $summary);
            $meta[$metaKey] = $billingMonth;
            $meta[sprintf('usage_threshold_%d_sent_at', $threshold)] = now()->toIso8601String();
        }

        $subscription->forceFill(['meta' => $meta])->save();
    }

    public function subscriptionForSite(Site $site): ?OrganizationSubscription
    {
        return $this->assignmentService->subscriptionForSite($site);
    }

    public function selectedPlanForSite(Site $site): ?Plan
    {
        $planId = (int) data_get($site->provider_meta, 'billing.selected_plan_id', 0);

        return $planId > 0 ? Plan::query()->find($planId) : null;
    }

    private function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMonth();
    }

    private function periodEnd(): CarbonImmutable
    {
        return CarbonImmutable::now()->endOfMonth();
    }

    private function stripe(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    private function requestMeterEventName(Plan $plan): string
    {
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', strtolower($plan->code)) ?: 'plan';

        return 'firephage_requests_'.$normalized;
    }
}
