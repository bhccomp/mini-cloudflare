<?php

namespace App\Services\Billing;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Models\Plan;
use App\Models\Site;
use Stripe\StripeClient;

class SiteCheckoutService
{
    public function __construct(
        private readonly OrganizationBillingService $organizationBillingService,
        private readonly SubscriptionSiteAssignmentService $assignmentService,
    ) {}

    public function createSiteSubscriptionCheckoutUrl(Site $site, Plan $plan): string
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

        $organization = $site->organization;
        if (! $organization) {
            throw new \RuntimeException('Site organization is missing.');
        }

        $statusHubUrl = SiteStatusHubPage::getUrl(['site_id' => $site->id], isAbsolute: true);

        if ($reusableSubscription = $this->assignmentService->reusableSubscriptionForPlan($site, $plan)) {
            $this->assignmentService->assignSite($reusableSubscription, $site);

            return $statusHubUrl.'&billing=covered';
        }

        $site->forceFill([
            'provider_meta' => array_merge((array) $site->provider_meta, [
                'billing' => array_merge((array) data_get($site->provider_meta, 'billing', []), [
                    'selected_plan_id' => $plan->id,
                    'selected_plan_code' => $plan->code,
                    'selected_interval' => 'month',
                    'checkout_required' => true,
                    'checkout_started_at' => now()->toIso8601String(),
                ]),
            ]),
        ])->save();

        $customerId = $this->organizationBillingService->ensureStripeCustomer($organization);

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'success_url' => $statusHubUrl.'?billing=success',
            'cancel_url' => $statusHubUrl.'?billing=cancelled',
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
                'site_id' => (string) $site->id,
                'plan_id' => (string) $plan->id,
                'plan_code' => (string) $plan->code,
                'billing_scope' => 'organization_plan',
                'billing_interval' => 'month',
            ],
            'subscription_data' => [
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                    'site_id' => (string) $site->id,
                    'plan_id' => (string) $plan->id,
                    'plan_code' => (string) $plan->code,
                    'billing_scope' => 'organization_plan',
                    'billing_interval' => 'month',
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
