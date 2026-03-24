<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Site;

class SiteBillingStateService
{
    public function __construct(
        private readonly SubscriptionSiteAssignmentService $assignmentService,
        private readonly OrganizationEntitlementService $entitlements,
    ) {}

    /**
     * @return array{status:string, subscription:\App\Models\OrganizationSubscription|null, plan:\App\Models\Plan|null, requires_checkout:bool, can_progress_protection:bool, message:string}
     */
    public function summaryForSite(Site $site): array
    {
        $mode = $this->entitlements->billingMode($site->organization);
        $subscription = $this->assignmentService->subscriptionForSite($site);
        $plan = $subscription?->plan ?? $this->entitlements->sitePlan($site);
        $status = (string) ($subscription?->status ?? '');
        $cancelAtPeriodEnd = (bool) data_get($subscription?->meta, 'cancel_at_period_end', false);
        $selectedPlanId = $plan?->id ?? (int) data_get($site->provider_meta, 'billing.selected_plan_id', 0);

        if ($mode === OrganizationEntitlementService::MODE_COMPED) {
            return [
                'status' => 'active',
                'subscription' => null,
                'plan' => $plan,
                'requires_checkout' => false,
                'can_progress_protection' => true,
                'message' => $plan
                    ? "{$plan->name} is active for this site through a manual FirePhage account override."
                    : 'This site is active through a manual FirePhage account override.',
            ];
        }

        if ($mode === OrganizationEntitlementService::MODE_MANUAL_TRIAL) {
            if ($this->entitlements->hasActiveManualTrial($site->organization)) {
                $trialEndsAt = $this->entitlements->trialEndsAt($site->organization);

                return [
                    'status' => 'trialing',
                    'subscription' => null,
                    'plan' => $plan,
                    'requires_checkout' => false,
                    'can_progress_protection' => true,
                    'message' => $plan && $trialEndsAt
                        ? "{$plan->name} is active on a manual trial until {$trialEndsAt->toFormattedDateString()}."
                        : 'This site is active on a manual trial.',
                ];
            }

            return [
                'status' => 'payment_required',
                'subscription' => null,
                'plan' => $plan,
                'requires_checkout' => true,
                'can_progress_protection' => false,
                'message' => $plan
                    ? "The manual trial for {$plan->name} has ended. Complete checkout before FirePhage continues protection setup."
                    : 'The manual trial has ended. Complete checkout before FirePhage continues protection setup.',
            ];
        }

        if ($selectedPlanId < 1 && ! $subscription) {
            return [
                'status' => 'not_set_up',
                'subscription' => null,
                'plan' => null,
                'requires_checkout' => false,
                'can_progress_protection' => true,
                'message' => 'No billing plan is attached to this site yet.',
            ];
        }

        if (in_array($status, ['active', 'trialing'], true) && $cancelAtPeriodEnd) {
            return [
                'status' => 'ending',
                'subscription' => $subscription,
                'plan' => $plan,
                'requires_checkout' => false,
                'can_progress_protection' => true,
                'message' => $plan
                    ? "{$plan->name} remains active until the current period ends, but it is set to cancel in Stripe."
                    : 'This subscription remains active until the current period ends, but it is set to cancel in Stripe.',
            ];
        }

        if (in_array($status, ['active', 'trialing'], true)) {
            return [
                'status' => $status,
                'subscription' => $subscription,
                'plan' => $plan,
                'requires_checkout' => false,
                'can_progress_protection' => true,
                'message' => $plan ? "{$plan->name} is active for this site." : 'Billing is active for this site.',
            ];
        }

        if ($status === 'checkout_completed') {
            return [
                'status' => $status,
                'subscription' => $subscription,
                'plan' => $plan,
                'requires_checkout' => false,
                'can_progress_protection' => false,
                'message' => 'Checkout has completed, but Stripe sync is still finalizing. Try again in a moment.',
            ];
        }

        if ($status === 'past_due') {
            return [
                'status' => $status,
                'subscription' => $subscription,
                'plan' => $plan,
                'requires_checkout' => false,
                'can_progress_protection' => false,
                'message' => 'Billing needs attention before FirePhage can continue provisioning or protection changes for this site.',
            ];
        }

        return [
            'status' => 'payment_required',
            'subscription' => $subscription,
            'plan' => $plan,
            'requires_checkout' => true,
            'can_progress_protection' => false,
            'message' => $plan
                ? "Complete checkout for {$plan->name} before FirePhage continues protection setup."
                : 'Choose a plan and complete checkout before FirePhage continues protection setup.',
        ];
    }

    public function canProgressProtection(Site $site): bool
    {
        return (bool) $this->summaryForSite($site)['can_progress_protection'];
    }

    public function blockedActionMessage(Site $site, string $action): string
    {
        $summary = $this->summaryForSite($site);

        return match ($summary['status']) {
            'checkout_completed' => "{$action} is temporarily unavailable while Stripe finalizes this subscription.",
            'past_due' => "{$action} is unavailable until the billing issue is resolved in Stripe.",
            'payment_required' => "{$action} is unavailable until checkout is completed for this site.",
            default => "{$action} is unavailable until billing is active for this site.",
        };
    }

    private function selectedPlanForSite(Site $site): ?Plan
    {
        return $this->entitlements->sitePlan($site);
    }
}
