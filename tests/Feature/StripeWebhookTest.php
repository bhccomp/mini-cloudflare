<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $payload = json_encode(['id' => 'evt_invalid', 'type' => 'invoice.paid'], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/stripe/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalid',
            ],
            content: $payload,
        );

        $response->assertStatus(400);
    }

    public function test_subscription_updated_event_syncs_organization_subscription(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $organization = Organization::create([
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
            'billing_email' => 'billing@acme.test',
        ]);

        $plan = Plan::create([
            'code' => 'pro',
            'name' => 'Pro',
            'headline' => 'Pro',
            'description' => 'Pro plan',
            'monthly_price_cents' => 4900,
            'yearly_price_cents' => 49000,
            'currency' => 'usd',
            'stripe_product_id' => 'prod_123',
            'stripe_monthly_price_id' => 'price_monthly_123',
            'features' => ['Managed WAF'],
            'is_active' => true,
        ]);

        $payload = json_encode([
            'id' => 'evt_subscription_updated',
            'object' => 'event',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'object' => 'subscription',
                    'customer' => 'cus_123',
                    'status' => 'active',
                    'current_period_end' => now()->addMonth()->timestamp,
                    'cancel_at' => null,
                    'canceled_at' => null,
                    'ended_at' => null,
                    'cancel_at_period_end' => false,
                    'livemode' => true,
                    'metadata' => [
                        'organization_id' => (string) $organization->id,
                        'plan_code' => $plan->code,
                    ],
                    'items' => [
                        'data' => [[
                            'price' => [
                                'id' => 'price_monthly_123',
                                'product' => 'prod_123',
                                'recurring' => [
                                    'interval' => 'month',
                                ],
                            ],
                        ]],
                    ],
                    'latest_invoice' => 'in_123',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/stripe/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, 'whsec_test_secret'),
            ],
            content: $payload,
        );

        $response->assertOk();

        $organization->refresh();

        $this->assertSame('cus_123', $organization->stripe_customer_id);

        $subscription = OrganizationSubscription::query()
            ->where('stripe_subscription_id', 'sub_123')
            ->first();

        $this->assertNotNull($subscription);
        $this->assertSame($organization->id, $subscription->organization_id);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('cus_123', $subscription->stripe_customer_id);
        $this->assertSame('price_monthly_123', data_get($subscription->meta, 'price_id'));
        $this->assertSame('prod_123', data_get($subscription->meta, 'product_id'));
        $this->assertSame('month', data_get($subscription->meta, 'interval'));
    }

    private function stripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
