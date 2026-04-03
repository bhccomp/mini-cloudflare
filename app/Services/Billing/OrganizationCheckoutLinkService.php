<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Plan;
use Stripe\StripeClient;

class OrganizationCheckoutLinkService
{
    public function __construct(
        private readonly OrganizationBillingService $organizationBillingService,
    ) {}

    public function createSubscriptionCheckoutUrl(Organization $organization, Plan $plan): string
    {
        if ((string) config('services.stripe.secret') === '') {
            throw new \RuntimeException('Stripe secret is not configured.');
        }

        if (! $plan->is_active) {
            throw new \RuntimeException('This plan is not active.');
        }

        if ($plan->is_contact_only) {
            throw new \RuntimeException('This plan requires manual sales onboarding.');
        }

        $priceId = (string) ($plan->stripe_monthly_price_id ?? '');

        if ($priceId === '') {
            throw new \RuntimeException('This plan is not synced to a Stripe monthly price yet.');
        }

        $customerId = $this->organizationBillingService->ensureStripeCustomer($organization);

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'success_url' => route('billing.checkout.complete', absolute: true),
            'cancel_url' => route('billing.checkout.cancelled', absolute: true),
            'payment_method_collection' => (int) $plan->trial_days > 0 ? 'always' : 'if_required',
            'line_items' => array_values(array_filter([
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
                $plan->stripe_request_overage_price_id
                    ? [
                        'price' => $plan->stripe_request_overage_price_id,
                    ]
                    : null,
            ])),
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'auto',
            'metadata' => [
                'organization_id' => (string) $organization->id,
                'plan_id' => (string) $plan->id,
                'plan_code' => (string) $plan->code,
                'billing_scope' => 'organization_plan',
                'billing_interval' => 'month',
                'created_via' => 'admin_payment_link',
            ],
            'subscription_data' => [
                ...($plan->trial_days > 0 ? ['trial_period_days' => (int) $plan->trial_days] : []),
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                    'plan_id' => (string) $plan->id,
                    'plan_code' => (string) $plan->code,
                    'billing_scope' => 'organization_plan',
                    'billing_interval' => 'month',
                    'created_via' => 'admin_payment_link',
                ],
            ],
        ]);

        return (string) $session->url;
    }

    private function stripe(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
