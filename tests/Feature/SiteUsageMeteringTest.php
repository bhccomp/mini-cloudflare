<?php

namespace Tests\Feature;

use App\Models\EdgeRequestLog;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use App\Services\Billing\SiteUsageMeteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteUsageMeteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_month_summary_calculates_overage_from_requests(): void
    {
        $plan = Plan::create([
            'code' => 'pro-usage',
            'name' => 'Pro',
            'headline' => 'Pro',
            'description' => 'Pro plan',
            'monthly_price_cents' => 4900,
            'yearly_price_cents' => 49000,
            'included_requests_per_month' => 10000,
            'overage_block_size' => 1000,
            'overage_price_cents' => 2,
            'currency' => 'usd',
            'features' => ['Managed WAF'],
            'is_active' => true,
        ]);

        $organization = Organization::create([
            'name' => 'Usage Org',
            'slug' => 'usage-org',
        ]);

        $site = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'usage.example.com',
            'name' => 'usage.example.com',
            'apex_domain' => 'usage.example.com',
            'origin_type' => 'ip',
            'origin_ip' => '203.0.113.10',
            'origin_url' => 'http://203.0.113.10',
            'origin_host' => 'usage.example.com',
            'status' => Site::STATUS_DRAFT,
        ]);

        $rows = [];
        for ($i = 0; $i < 11250; $i++) {
            $rows[] = [
                'site_id' => $site->id,
                'event_at' => now()->subDay(),
                'ip' => '198.51.100.10',
                'country' => 'US',
                'method' => 'GET',
                'host' => 'usage.example.com',
                'path' => '/',
                'status_code' => 200,
                'action' => 'ALLOW',
                'rule' => null,
                'user_agent' => 'PHPUnit',
                'meta' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            EdgeRequestLog::query()->insert($chunk);
        }

        $summary = app(SiteUsageMeteringService::class)->currentMonthSummary($site, $plan);

        $this->assertSame(11250, $summary['requests']);
        $this->assertSame(10000, $summary['included_requests']);
        $this->assertSame(1250, $summary['overage_requests']);
        $this->assertSame(2, $summary['overage_blocks']);
        $this->assertSame(4, $summary['estimated_overage_cents']);
    }

    public function test_current_month_summary_aggregates_requests_across_sites_on_same_subscription(): void
    {
        $plan = Plan::create([
            'code' => 'growth-usage',
            'name' => 'Growth',
            'headline' => 'Growth',
            'description' => 'Growth plan',
            'monthly_price_cents' => 9900,
            'yearly_price_cents' => 99000,
            'included_websites' => 3,
            'included_requests_per_month' => 10000,
            'overage_block_size' => 1000,
            'overage_price_cents' => 2,
            'currency' => 'usd',
            'features' => ['Managed WAF'],
            'is_active' => true,
        ]);

        $organization = Organization::create([
            'name' => 'Aggregate Org',
            'slug' => 'aggregate-org',
        ]);

        $siteA = Site::create([
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

        $siteB = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'two.example.com',
            'name' => 'two.example.com',
            'apex_domain' => 'two.example.com',
            'origin_type' => 'ip',
            'origin_ip' => '203.0.113.11',
            'origin_url' => 'http://203.0.113.11',
            'origin_host' => 'two.example.com',
            'status' => Site::STATUS_DRAFT,
        ]);

        $subscription = OrganizationSubscription::create([
            'organization_id' => $organization->id,
            'site_id' => $siteA->id,
            'plan_id' => $plan->id,
            'stripe_customer_id' => 'cus_aggregate',
            'stripe_subscription_id' => 'sub_aggregate',
            'status' => 'active',
            'renews_at' => now()->addMonth(),
            'meta' => ['interval' => 'month'],
        ]);

        $subscription->sites()->syncWithoutDetaching([$siteA->id, $siteB->id]);

        $rows = [];
        foreach ([
            [$siteA->id, 7000, 'one.example.com'],
            [$siteB->id, 5000, 'two.example.com'],
        ] as [$siteId, $count, $host]) {
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'site_id' => $siteId,
                    'event_at' => now()->subDay(),
                    'ip' => '198.51.100.10',
                    'country' => 'US',
                    'method' => 'GET',
                    'host' => $host,
                    'path' => '/',
                    'status_code' => 200,
                    'action' => 'ALLOW',
                    'rule' => null,
                    'user_agent' => 'PHPUnit',
                    'meta' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            EdgeRequestLog::query()->insert($chunk);
        }

        $summary = app(SiteUsageMeteringService::class)->currentMonthSummary($siteA, $plan, $subscription->fresh('plan', 'sites'));

        $this->assertSame(12000, $summary['requests']);
        $this->assertSame(2, $summary['covered_sites_count']);
        $this->assertSame(10000, $summary['included_requests']);
        $this->assertSame(2000, $summary['overage_requests']);
        $this->assertSame(2, $summary['overage_blocks']);
        $this->assertSame(4, $summary['estimated_overage_cents']);
    }
}
