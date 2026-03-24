<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Site;
use App\Services\Billing\OrganizationBillingService;
use App\Services\Billing\SiteCheckoutService;
use App\Services\Billing\SubscriptionSiteAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_payload_collects_payment_method_and_trial_when_plan_has_trial_days(): void
    {
        config()->set('services.stripe.secret', 'sk_test_123');

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'billing_email' => 'billing@acme.test',
        ]);

        $site = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'origin_type' => 'ip',
            'origin_ip' => '203.0.113.10',
            'origin_url' => 'http://203.0.113.10',
            'origin_host' => 'example.com',
            'status' => Site::STATUS_DRAFT,
        ]);

        $plan = Plan::create([
            'code' => 'growth-trial-test',
            'name' => 'Growth',
            'headline' => 'Growth',
            'description' => 'Growth plan',
            'monthly_price_cents' => 9900,
            'yearly_price_cents' => 99000,
            'trial_days' => 14,
            'included_websites' => 3,
            'currency' => 'usd',
            'stripe_monthly_price_id' => 'price_monthly_growth',
            'features' => ['Managed WAF'],
            'is_active' => true,
        ]);

        $billing = $this->mock(OrganizationBillingService::class);
        $billing->shouldReceive('ensureStripeCustomer')
            ->once()
            ->withArgs(fn (Organization $passed): bool => $passed->is($organization))
            ->andReturn('cus_123');

        $assignment = $this->mock(SubscriptionSiteAssignmentService::class);
        $assignment->shouldReceive('reusableSubscriptionForPlan')
            ->once()
            ->andReturnNull();

        $capturedPayload = null;

        $service = new class($billing, $assignment, $capturedPayload) extends SiteCheckoutService
        {
            /**
             * @var array<string, mixed>|null
             */
            private $capturedPayload;

            /**
             * @param array<string, mixed>|null $capturedPayload
             */
            public function __construct(
                OrganizationBillingService $organizationBillingService,
                SubscriptionSiteAssignmentService $assignmentService,
                &$capturedPayload,
            ) {
                $this->capturedPayload = &$capturedPayload;
                parent::__construct($organizationBillingService, $assignmentService);
            }

            /**
             * @param array<string, mixed> $payload
             */
            protected function createCheckoutSession(array $payload): object
            {
                $this->capturedPayload = $payload;

                return (object) ['url' => 'https://checkout.stripe.test/session'];
            }
        };

        $url = $service->createSiteSubscriptionCheckoutUrl($site, $plan);

        $this->assertSame('https://checkout.stripe.test/session', $url);
        $this->assertSame('always', data_get($capturedPayload, 'payment_method_collection'));
        $this->assertSame(14, data_get($capturedPayload, 'subscription_data.trial_period_days'));
        $this->assertSame('price_monthly_growth', data_get($capturedPayload, 'line_items.0.price'));
    }

    public function test_checkout_payload_skips_trial_settings_when_plan_has_no_trial_days(): void
    {
        config()->set('services.stripe.secret', 'sk_test_123');

        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'billing_email' => 'billing@acme.test',
        ]);

        $site = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'origin_type' => 'ip',
            'origin_ip' => '203.0.113.10',
            'origin_url' => 'http://203.0.113.10',
            'origin_host' => 'example.com',
            'status' => Site::STATUS_DRAFT,
        ]);

        $plan = Plan::create([
            'code' => 'starter-no-trial-test',
            'name' => 'Starter',
            'headline' => 'Starter',
            'description' => 'Starter plan',
            'monthly_price_cents' => 2900,
            'yearly_price_cents' => 29000,
            'trial_days' => 0,
            'included_websites' => 1,
            'currency' => 'usd',
            'stripe_monthly_price_id' => 'price_monthly_starter',
            'features' => ['Managed WAF'],
            'is_active' => true,
        ]);

        $billing = $this->mock(OrganizationBillingService::class);
        $billing->shouldReceive('ensureStripeCustomer')->once()->andReturn('cus_123');

        $assignment = $this->mock(SubscriptionSiteAssignmentService::class);
        $assignment->shouldReceive('reusableSubscriptionForPlan')->once()->andReturnNull();

        $capturedPayload = null;

        $service = new class($billing, $assignment, $capturedPayload) extends SiteCheckoutService
        {
            /**
             * @var array<string, mixed>|null
             */
            private $capturedPayload;

            /**
             * @param array<string, mixed>|null $capturedPayload
             */
            public function __construct(
                OrganizationBillingService $organizationBillingService,
                SubscriptionSiteAssignmentService $assignmentService,
                &$capturedPayload,
            ) {
                $this->capturedPayload = &$capturedPayload;
                parent::__construct($organizationBillingService, $assignmentService);
            }

            /**
             * @param array<string, mixed> $payload
             */
            protected function createCheckoutSession(array $payload): object
            {
                $this->capturedPayload = $payload;

                return (object) ['url' => 'https://checkout.stripe.test/session'];
            }
        };

        $service->createSiteSubscriptionCheckoutUrl($site, $plan);

        $this->assertSame('if_required', data_get($capturedPayload, 'payment_method_collection'));
        $this->assertNull(data_get($capturedPayload, 'subscription_data.trial_period_days'));
    }
}
