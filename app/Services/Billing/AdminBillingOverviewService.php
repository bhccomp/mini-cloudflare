<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\DemoModeService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class AdminBillingOverviewService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private static array $stripeSnapshotCache = [];

    public function __construct(
        private readonly DemoModeService $demoMode,
        private readonly OrganizationBillingService $organizationBillingService,
    ) {}

    /**
     * @return array{mrr_cents:int, trial_value_cents:int, subscribed_organizations:int, subscribed_users:int, past_due_organizations:int, trialing_organizations:int, comped_organizations:int}
     */
    public function headlineMetrics(): array
    {
        $organizations = $this->organizationsWithBilling()->all();

        $subscribedOrganizationIds = [];
        $pastDueOrganizations = 0;
        $trialingOrganizations = 0;
        $compedOrganizations = 0;
        $mrrCents = 0;
        $trialValueCents = 0;

        foreach ($organizations as $organization) {
            $summary = $this->summaryForOrganization($organization);
            $status = (string) $summary['status'];

            if ($this->includedInPaidRevenueMetrics($organization, $status)) {
                $subscribedOrganizationIds[] = $organization->id;
                $mrrCents += $this->monthlyValueCents($summary['subscription'], $summary['plan']);
            }

            if ($status === 'trialing') {
                $trialingOrganizations++;
                if (! $this->demoMode->isDemoOrganization($organization)) {
                    $trialValueCents += $this->monthlyValueCents($summary['subscription'], $summary['plan']);
                }
            }

            if ($status === 'past_due') {
                $pastDueOrganizations++;
            }

            if ($status === OrganizationEntitlementService::MODE_COMPED) {
                $compedOrganizations++;
            }
        }

        $subscribedOrganizationIds = array_values(array_unique(array_map('intval', $subscribedOrganizationIds)));

        return [
            'mrr_cents' => $mrrCents,
            'trial_value_cents' => $trialValueCents,
            'subscribed_organizations' => count($subscribedOrganizationIds),
            'subscribed_users' => $this->subscribedUsersCount($subscribedOrganizationIds),
            'past_due_organizations' => $pastDueOrganizations,
            'trialing_organizations' => $trialingOrganizations,
            'comped_organizations' => $compedOrganizations,
        ];
    }

    /**
     * @return Collection<int, array{organization:\App\Models\Organization, plan:?Plan, subscription:?OrganizationSubscription, status:string, status_label:string, plan_label:?string, source_label:string, stripe_customer_email:?string, monthly_amount_cents:int, covered_sites:int, included_sites:int, renews_at:?string}>
     */
    public function organizationRows(): Collection
    {
        return $this->organizationsWithBilling()
            ->reject(fn (Organization $organization): bool => $this->demoMode->isDemoOrganization($organization))
            ->map(function (Organization $organization): array {
                $summary = $this->summaryForOrganization($organization);
                $subscription = $summary['subscription'];
                $plan = $summary['plan'];

                return [
                    'organization' => $organization,
                    'plan' => $plan,
                    'subscription' => $subscription,
                    'status' => (string) $summary['status'],
                    'status_label' => (string) $summary['status_label'],
                    'plan_label' => $summary['plan_label'],
                    'source_label' => (string) $summary['source_label'],
                    'stripe_customer_email' => $summary['stripe_customer_email'],
                    'monthly_amount_cents' => $this->includedInPaidRevenueMetrics($organization, (string) $summary['status'])
                        ? $this->monthlyValueCents($subscription, $plan)
                        : 0,
                    'covered_sites' => $subscription?->assignableSiteCount() ?? 0,
                    'included_sites' => $subscription?->includedWebsiteSlots() ?? ($plan?->includedWebsites() ?? 0),
                    'renews_at' => optional($subscription?->renews_at)?->toDayDateTimeString(),
                ];
            })
            ->sortBy([
                fn (array $row): int => match ($row['status']) {
                    'past_due' => 0,
                    'trialing' => 1,
                    'active' => 2,
                    OrganizationEntitlementService::MODE_MANUAL_TRIAL => 3,
                    OrganizationEntitlementService::MODE_COMPED => 4,
                    'checkout_completed' => 5,
                    default => 6,
                },
                fn (array $row): string => strtolower($row['organization']->name),
            ])
            ->values();
    }

    /**
     * @return array{subscription:?OrganizationSubscription, plan:?Plan, status:string, status_label:string, plan_label:?string, source_label:string, stripe_customer_email:?string}
     */
    public function summaryForOrganization(Organization $organization): array
    {
        $mode = (string) data_get($organization->settings, 'billing_mode', OrganizationEntitlementService::MODE_STRIPE);
        $subscription = $this->currentSubscription($organization);
        $assignedPlan = $this->assignedPlan($organization);
        $plan = $subscription?->plan ?? $assignedPlan;

        if ($mode === OrganizationEntitlementService::MODE_COMPED) {
            return [
                'subscription' => null,
                'plan' => $plan,
                'status' => OrganizationEntitlementService::MODE_COMPED,
                'status_label' => 'Comped',
                'plan_label' => $plan?->name,
                'source_label' => 'Local',
                'stripe_customer_email' => null,
            ];
        }

        if ($mode === OrganizationEntitlementService::MODE_MANUAL_TRIAL) {
            return [
                'subscription' => null,
                'plan' => $plan,
                'status' => OrganizationEntitlementService::MODE_MANUAL_TRIAL,
                'status_label' => 'Manual Trial',
                'plan_label' => $plan?->name,
                'source_label' => 'Local',
                'stripe_customer_email' => null,
            ];
        }

        $stripe = $this->stripeSnapshot($organization, $subscription);

        if (($stripe['source'] ?? 'none') !== 'none') {
            return [
                'subscription' => $subscription,
                'plan' => $stripe['plan'] ?? $plan,
                'status' => (string) ($stripe['status'] ?? ($subscription?->status ?? 'not_set_up')),
                'status_label' => $this->stripeStatusLabel((string) ($stripe['status'] ?? ($subscription?->status ?? 'not_set_up'))),
                'plan_label' => ($stripe['plan'] ?? $plan)?->name,
                'source_label' => (string) ($stripe['source_label'] ?? 'Stripe'),
                'stripe_customer_email' => $stripe['customer_email'] ?? null,
            ];
        }

        $status = (string) ($subscription?->status ?? 'not_set_up');

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'status' => $status,
            'status_label' => $this->stripeStatusLabel($status),
            'plan_label' => $plan?->name,
            'source_label' => $subscription?->stripe_subscription_id || $subscription?->stripe_customer_id ? 'Mismatch' : 'Local Only',
            'stripe_customer_email' => null,
        ];
    }

    public function currentSubscription(Organization $organization): ?OrganizationSubscription
    {
        if ($organization->relationLoaded('subscriptions')) {
            /** @var EloquentCollection<int, OrganizationSubscription> $subscriptions */
            $subscriptions = $organization->getRelation('subscriptions');

            return $subscriptions
                ->sort(function (OrganizationSubscription $a, OrganizationSubscription $b): int {
                    $priorityA = $this->subscriptionPriority((string) $a->status);
                    $priorityB = $this->subscriptionPriority((string) $b->status);

                    if ($priorityA !== $priorityB) {
                        return $priorityA <=> $priorityB;
                    }

                    return $b->id <=> $a->id;
                })
                ->first();
        }

        return $organization->subscriptions()
            ->with('plan')
            ->orderByRaw("case when status in ('active', 'trialing', 'past_due', 'checkout_completed') then 0 else 1 end")
            ->latest('id')
            ->first();
    }

    private function organizationsWithBilling(): Collection
    {
        return Organization::query()
            ->with([
                'subscriptions' => fn ($query) => $query->with('plan', 'sites')->latest('id'),
                'users',
            ])
            ->orderBy('name')
            ->get();
    }

    private function assignedPlan(Organization $organization): ?Plan
    {
        $assignedPlanId = (int) data_get($organization->settings, 'assigned_plan_id', 0);

        if ($assignedPlanId < 1) {
            return null;
        }

        return Plan::query()->find($assignedPlanId);
    }

    private function monthlyValueCents(?OrganizationSubscription $subscription, ?Plan $plan): int
    {
        if (! $plan) {
            return 0;
        }

        $interval = (string) data_get($subscription?->meta, 'interval', 'month');

        if ($interval === 'year') {
            return (int) round(((int) $plan->yearly_price_cents) / 12);
        }

        return (int) $plan->monthly_price_cents;
    }

    /**
     * @param  array<int, int>  $organizationIds
     */
    private function subscribedUsersCount(array $organizationIds): int
    {
        if ($organizationIds === []) {
            return 0;
        }

        return User::query()
            ->whereHas('organizations', fn ($query) => $query->whereIn('organizations.id', $organizationIds))
            ->distinct('users.id')
            ->count('users.id');
    }

    private function stripeStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'trialing' => 'Trialing',
            'past_due' => 'Past Due',
            'checkout_completed' => 'Syncing',
            'canceled' => 'Canceled',
            default => 'Not Set Up',
        };
    }

    private function subscriptionPriority(string $status): int
    {
        return match ($status) {
            'active', 'trialing', 'past_due', 'checkout_completed' => 0,
            default => 1,
        };
    }

    private function includedInPaidRevenueMetrics(Organization $organization, string $status): bool
    {
        if ($this->demoMode->isDemoOrganization($organization)) {
            return false;
        }

        return in_array($status, ['active', 'past_due'], true);
    }

    /**
     * @return array{source:string, source_label:string, status:?string, customer_email:?string, plan:?Plan}|array{source:string}
     */
    private function stripeSnapshot(Organization $organization, ?OrganizationSubscription $subscription): array
    {
        if (! $this->organizationBillingService->hasStripeConfigured()) {
            return ['source' => 'none'];
        }

        $cacheKey = $organization->id;

        if (array_key_exists($cacheKey, self::$stripeSnapshotCache)) {
            return self::$stripeSnapshotCache[$cacheKey];
        }

        $customerId = (string) ($organization->stripe_customer_id
            ?: $subscription?->stripe_customer_id
            ?: $this->organizationBillingService->resolveCustomerId($organization)
            ?: '');
        $subscriptionId = (string) ($subscription?->stripe_subscription_id ?: '');

        if ($customerId === '' && $subscriptionId === '') {
            return self::$stripeSnapshotCache[$cacheKey] = ['source' => 'none'];
        }

        try {
            $stripe = $this->stripe();
            $customer = $customerId !== '' ? $stripe->customers->retrieve($customerId, []) : null;
            $stripeSubscription = null;

            if ($subscriptionId !== '') {
                $stripeSubscription = $stripe->subscriptions->retrieve($subscriptionId, []);
            } elseif ($customerId !== '') {
                $subscriptions = $stripe->subscriptions->all([
                    'customer' => $customerId,
                    'status' => 'all',
                    'limit' => 10,
                ])->data;

                $stripeSubscription = collect($subscriptions)
                    ->sortByDesc(fn ($item) => (int) ($item->created ?? 0))
                    ->sortBy(fn ($item) => $this->subscriptionPriority((string) ($item->status ?? '')))
                    ->first();
            }

            if (! $stripeSubscription || ! is_object($stripeSubscription) || (isset($stripeSubscription->id) && (string) $stripeSubscription->id === '')) {
                return self::$stripeSnapshotCache[$cacheKey] = [
                    'source' => 'mismatch',
                    'source_label' => 'Mismatch',
                    'status' => $subscription?->status,
                    'customer_email' => is_object($customer) ? (string) ($customer->email ?? '') : null,
                    'plan' => $subscription?->plan,
                ];
            }

            $price = $stripeSubscription->items->data[0]->price ?? null;
            $priceId = isset($price->id) ? (string) $price->id : null;
            $productId = isset($price->product) ? (string) $price->product : null;
            $plan = $this->resolvePlan(
                metadata: $stripeSubscription->metadata ?? [],
                priceId: $priceId,
                productId: $productId,
            );

            $localSubscriptionId = (string) ($subscription?->stripe_subscription_id ?? '');
            $localCustomerId = (string) ($subscription?->stripe_customer_id ?: $organization->stripe_customer_id ?: '');
            $sourceLabel = ($localSubscriptionId !== '' && $localSubscriptionId === (string) $stripeSubscription->id && ($localCustomerId === '' || $localCustomerId === (string) ($stripeSubscription->customer ?? '')))
                ? 'Verified'
                : 'Stripe';

            return self::$stripeSnapshotCache[$cacheKey] = [
                'source' => 'stripe',
                'source_label' => $sourceLabel,
                'status' => (string) ($stripeSubscription->status ?? ''),
                'customer_email' => is_object($customer) ? (string) ($customer->email ?? '') : null,
                'plan' => $plan,
            ];
        } catch (ApiErrorException) {
            return self::$stripeSnapshotCache[$cacheKey] = [
                'source' => 'mismatch',
                'source_label' => 'Mismatch',
                'status' => $subscription?->status,
                'customer_email' => null,
                'plan' => $subscription?->plan,
            ];
        }
    }

    private function resolvePlan(mixed $metadata, ?string $priceId, ?string $productId): ?Plan
    {
        $planId = data_get($metadata, 'plan_id');

        if ($planId) {
            return Plan::query()->find($planId);
        }

        $planCode = data_get($metadata, 'plan_code');

        if ($planCode) {
            return Plan::query()->where('code', $planCode)->first();
        }

        if ($priceId) {
            return Plan::query()
                ->where('stripe_monthly_price_id', $priceId)
                ->orWhere('stripe_yearly_price_id', $priceId)
                ->first();
        }

        if ($productId) {
            return Plan::query()->where('stripe_product_id', $productId)->first();
        }

        return null;
    }

    private function stripe(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
