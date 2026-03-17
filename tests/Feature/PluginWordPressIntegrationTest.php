<?php

namespace Tests\Feature;

use App\Models\EdgeRequestLog;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\PluginSiteConnection;
use App\Models\Site;
use App\Models\SiteAnalyticsMetric;
use App\Services\WordPress\PluginSiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginWordPressIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_site_does_not_receive_live_plugin_pro_telemetry(): void
    {
        [$site, $connection] = $this->makeConnectedSite();

        SiteAnalyticsMetric::query()->create([
            'site_id' => $site->id,
            'total_requests_24h' => 120,
            'cached_requests_24h' => 60,
            'origin_requests_24h' => 60,
            'cache_hit_ratio' => 50,
            'captured_at' => now(),
        ]);

        EdgeRequestLog::query()->create([
            'site_id' => $site->id,
            'event_at' => now()->subMinutes(5),
            'ip' => '203.0.113.5',
            'country' => 'US',
            'method' => 'POST',
            'path' => '/wp-login.php',
            'status_code' => 403,
            'action' => 'BLOCK',
        ]);

        $firewall = app(PluginSiteService::class)->firewallSummary($connection);
        $performance = app(PluginSiteService::class)->performanceSummary($connection);

        $this->assertFalse($firewall['pro_enabled']);
        $this->assertSame(0, $firewall['metrics']['requests_blocked']);
        $this->assertSame([], $firewall['activity']);
        $this->assertFalse($performance['pro_enabled']);
        $this->assertSame(0, $performance['summary']['requests_24h']);
        $this->assertSame([], $performance['cache_rules']);
    }

    public function test_paid_assigned_site_receives_live_plugin_pro_telemetry(): void
    {
        [$site, $connection] = $this->makeConnectedSite([
            'last_report_payload' => [
                'generated_at' => now()->toIso8601String(),
                'site' => [
                    'wp_version' => '6.8.1',
                    'plugin_version' => '1.4.0',
                ],
                'health' => [
                    'summary' => ['good' => 8, 'warning' => 1, 'critical' => 0],
                    'updates' => ['core_updates' => 0, 'plugin_updates' => 2, 'theme_updates' => 1],
                    'core_checksum' => ['status' => 'ok', 'summary' => 'Core checksums matched.'],
                ],
                'malware_scan' => [
                    'status' => 'clean',
                    'scanned_files' => 4200,
                    'suspicious_files' => 1,
                    'findings' => [
                        [
                            'file' => 'wp-content/plugins/example/bad.php',
                            'type' => 'malware',
                            'confidence' => 'high',
                            'reasons' => ['eval', 'base64_decode'],
                        ],
                    ],
                ],
            ],
        ]);

        $plan = Plan::query()->create([
            'code' => 'growth-plugin-test',
            'name' => 'Growth Plugin Test',
            'monthly_price_cents' => 4900,
            'yearly_price_cents' => 49000,
            'included_websites' => 3,
            'currency' => 'usd',
            'cta_label' => 'Get Started',
            'is_active' => true,
        ]);

        $subscription = OrganizationSubscription::query()->create([
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'meta' => ['interval' => 'month'],
        ]);
        $subscription->sites()->attach($site->id);

        SiteAnalyticsMetric::query()->create([
            'site_id' => $site->id,
            'total_requests_24h' => 200,
            'cached_requests_24h' => 150,
            'origin_requests_24h' => 50,
            'cache_hit_ratio' => 75,
            'captured_at' => now(),
        ]);

        EdgeRequestLog::query()->create([
            'site_id' => $site->id,
            'event_at' => now()->subMinutes(5),
            'ip' => '203.0.113.5',
            'country' => 'US',
            'method' => 'POST',
            'path' => '/wp-login.php',
            'status_code' => 403,
            'action' => 'BLOCK',
        ]);

        $firewall = app(PluginSiteService::class)->firewallSummary($connection->fresh('site.analyticsMetric'));
        $performance = app(PluginSiteService::class)->performanceSummary($connection->fresh('site.analyticsMetric'));
        $findings = app(PluginSiteService::class)->wordpressMalwareFindingsForSite($site->fresh('pluginConnection'));

        $this->assertTrue($firewall['pro_enabled']);
        $this->assertSame(1, $firewall['metrics']['requests_blocked']);
        $this->assertCount(1, $firewall['activity']);
        $this->assertTrue($performance['pro_enabled']);
        $this->assertSame(200, $performance['summary']['requests_24h']);
        $this->assertCount(4, $performance['cache_rules']);
        $this->assertSame('Global edge cache', $performance['cache_rules'][0]['path']);
        $this->assertSame('wp-content/plugins/example/bad.php', $findings[0]['file']);
    }

    public function test_connected_site_can_fetch_plugin_status_summary(): void
    {
        [$site] = $this->makeConnectedSite();

        $plan = Plan::query()->create([
            'code' => 'starter-status-test',
            'name' => 'Starter',
            'headline' => 'Starter',
            'description' => 'Starter plan',
            'monthly_price_cents' => 2900,
            'yearly_price_cents' => 29000,
            'included_websites' => 1,
            'currency' => 'usd',
            'is_active' => true,
        ]);

        $subscription = OrganizationSubscription::query()->create([
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'meta' => ['interval' => 'month'],
        ]);
        $subscription->sites()->attach($site->id);

        $this->withToken('plugin-token')
            ->getJson('/api/plugin/status?site_id='.$site->id)
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('site.id', (string) $site->id)
            ->assertJsonPath('plugin.status', 'connected')
            ->assertJsonPath('billing.pro_enabled', true)
            ->assertJsonPath('billing.plan_name', 'Starter')
            ->assertJsonPath('capabilities.report_upload', true);
    }

    /**
     * @return array{0: Site, 1: PluginSiteConnection}
     */
    private function makeConnectedSite(array $connectionOverrides = []): array
    {
        $organization = Organization::query()->create([
            'name' => 'WordPress Org',
            'slug' => 'wordpress-org',
        ]);

        $site = Site::query()->create([
            'organization_id' => $organization->id,
            'name' => 'store.example.com',
            'display_name' => 'Store',
            'apex_domain' => 'store.example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'status' => Site::STATUS_ACTIVE,
            'onboarding_status' => Site::ONBOARDING_LIVE,
        ]);

        $connection = PluginSiteConnection::query()->create(array_merge([
            'site_id' => $site->id,
            'site_token_hash' => hash('sha256', 'plugin-token'),
            'status' => 'connected',
            'home_url' => 'https://store.example.com',
            'site_url' => 'https://store.example.com',
            'admin_email' => 'ops@example.com',
            'plugin_version' => '1.4.0',
            'last_connected_at' => now(),
            'last_seen_at' => now(),
            'last_reported_at' => now(),
        ], $connectionOverrides));

        return [$site, $connection];
    }
}
