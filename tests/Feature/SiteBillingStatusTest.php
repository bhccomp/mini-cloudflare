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

class SiteBillingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_hub_shows_payment_required_for_unpaid_site_plan(): void
    {
        [$user, $site, $plan] = $this->seedSiteWithPlan();

        $site->forceFill([
            'provider_meta' => array_merge((array) $site->provider_meta, [
                'billing' => [
                    'selected_plan_id' => $plan->id,
                    'selected_plan_code' => $plan->code,
                    'selected_interval' => 'month',
                    'checkout_required' => true,
                ],
            ]),
        ])->save();

        $this->actingAs($user)
            ->get('/app/status-hub?site_id='.$site->id)
            ->assertOk()
            ->assertSee('Site Billing')
            ->assertSee('Payment Required')
            ->assertSee('Complete checkout');
    }

    public function test_status_hub_shows_paid_for_active_site_subscription(): void
    {
        [$user, $site, $plan] = $this->seedSiteWithPlan();

        OrganizationSubscription::create([
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'plan_id' => $plan->id,
            'stripe_customer_id' => 'cus_123',
            'stripe_subscription_id' => 'sub_123',
            'status' => 'active',
            'renews_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->get('/app/status-hub?site_id='.$site->id)
            ->assertOk()
            ->assertSee('Site Billing')
            ->assertSee('Paid')
            ->assertSee($plan->name);
    }

    private function seedSiteWithPlan(): array
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
            'code' => 'pro-'.$organization->id,
            'name' => 'Pro',
            'headline' => 'Pro',
            'description' => 'Pro plan',
            'monthly_price_cents' => 4900,
            'yearly_price_cents' => 49000,
            'currency' => 'usd',
            'stripe_monthly_price_id' => 'price_monthly_'.$organization->id,
            'features' => ['Managed WAF'],
            'is_active' => true,
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

        return [$user, $site, $plan];
    }
}
