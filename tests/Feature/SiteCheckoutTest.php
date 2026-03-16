<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_site_checkout_redirect(): void
    {
        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'billing_email' => 'billing@acme.test',
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationAccessService::ROLE_OWNER,
            'permissions' => json_encode(
                app(OrganizationAccessService::class)->defaultPermissionsForRole(OrganizationAccessService::ROLE_OWNER),
                JSON_THROW_ON_ERROR,
            ),
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
            'code' => 'pro',
            'name' => 'Pro',
            'headline' => 'Pro',
            'description' => 'Pro plan',
            'monthly_price_cents' => 4900,
            'yearly_price_cents' => 49000,
            'currency' => 'usd',
            'stripe_monthly_price_id' => 'price_monthly_123',
            'features' => ['Managed WAF'],
            'is_active' => true,
        ]);

        $this->instance(
            \App\Services\Billing\SiteCheckoutService::class,
            \Mockery::mock(\App\Services\Billing\SiteCheckoutService::class, function ($mock) use ($site, $plan): void {
                $mock->shouldReceive('createSiteSubscriptionCheckoutUrl')
                    ->once()
                    ->withArgs(fn (Site $passedSite, Plan $passedPlan): bool => $passedSite->is($site) && $passedPlan->is($plan))
                    ->andReturn('https://checkout.stripe.test/session');
            }),
        );

        $this->actingAs($user)
            ->get(route('app.sites.checkout', ['site' => $site, 'plan' => $plan]))
            ->assertRedirect('https://checkout.stripe.test/session');
    }

    public function test_checkout_route_reuses_existing_plan_slot_when_available(): void
    {
        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'billing_email' => 'billing@acme.test',
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationAccessService::ROLE_OWNER,
            'permissions' => json_encode(
                app(OrganizationAccessService::class)->defaultPermissionsForRole(OrganizationAccessService::ROLE_OWNER),
                JSON_THROW_ON_ERROR,
            ),
        ]);

        $plan = Plan::create([
            'code' => 'growth-'.$organization->id,
            'name' => 'Growth',
            'headline' => 'Growth',
            'description' => 'Growth plan',
            'monthly_price_cents' => 9900,
            'yearly_price_cents' => 99000,
            'included_websites' => 3,
            'currency' => 'usd',
            'stripe_monthly_price_id' => 'price_monthly_growth',
            'features' => ['Managed WAF'],
            'is_active' => true,
        ]);

        $existingSite = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'one.example.com',
            'name' => 'one.example.com',
            'apex_domain' => 'one.example.com',
            'origin_type' => 'ip',
            'origin_ip' => '203.0.113.10',
            'origin_url' => 'http://203.0.113.10',
            'origin_host' => 'one.example.com',
            'status' => Site::STATUS_DRAFT,
        ]);

        $newSite = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'two.example.com',
            'name' => 'two.example.com',
            'apex_domain' => 'two.example.com',
            'origin_type' => 'ip',
            'origin_ip' => '203.0.113.11',
            'origin_url' => 'http://203.0.113.11',
            'origin_host' => 'two.example.com',
            'status' => Site::STATUS_DRAFT,
            'provider_meta' => [
                'billing' => [
                    'selected_plan_id' => $plan->id,
                    'selected_plan_code' => $plan->code,
                    'selected_interval' => 'month',
                    'checkout_required' => true,
                ],
            ],
        ]);

        $subscription = OrganizationSubscription::create([
            'organization_id' => $organization->id,
            'site_id' => $existingSite->id,
            'plan_id' => $plan->id,
            'stripe_customer_id' => 'cus_growth',
            'stripe_subscription_id' => 'sub_growth',
            'status' => 'active',
            'renews_at' => now()->addMonth(),
            'meta' => ['interval' => 'month'],
        ]);

        $subscription->sites()->syncWithoutDetaching([$existingSite->id]);

        $this->actingAs($user)
            ->get(route('app.sites.checkout', ['site' => $newSite, 'plan' => $plan]))
            ->assertRedirect('/app/status-hub?site_id='.$newSite->id.'&billing=covered');

        $this->assertDatabaseHas('organization_subscription_site', [
            'organization_subscription_id' => $subscription->id,
            'site_id' => $newSite->id,
        ]);

        $this->assertFalse((bool) data_get($newSite->fresh()->provider_meta, 'billing.checkout_required', true));
    }
}
