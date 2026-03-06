<?php

namespace Tests\Unit;

use App\Models\AlertChannel;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\EdgeRequestLog;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteAnalyticsMetric;
use App\Models\SiteAvailabilityCheck;
use App\Models\SiteEvent;
use App\Models\SiteFirewallRule;
use App\Models\User;
use App\Services\Sites\SiteDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteDeletionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_site_removes_site_scoped_local_records(): void
    {
        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'www_enabled' => true,
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
            'status' => Site::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
            'current_organization_id' => $organization->id,
            'selected_site_id' => $site->id,
        ]);

        AlertChannel::create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'name' => 'Site Email',
            'type' => 'email',
            'is_active' => true,
        ]);

        $rule = AlertRule::create([
            'site_id' => $site->id,
            'name' => 'Blocked requests',
            'event_type' => 'blocked_requests',
            'threshold' => 10,
            'window_minutes' => 5,
            'is_active' => true,
        ]);

        AlertEvent::create([
            'site_id' => $site->id,
            'alert_rule_id' => $rule->id,
            'severity' => 'warning',
            'title' => 'Blocked spike',
            'occurred_at' => now(),
        ]);

        SiteEvent::create([
            'site_id' => $site->id,
            'type' => 'status',
            'severity' => 'info',
            'title' => 'Updated',
            'occurred_at' => now(),
        ]);

        SiteAnalyticsMetric::create([
            'site_id' => $site->id,
            'blocked_requests_24h' => 1,
            'allowed_requests_24h' => 2,
            'total_requests_24h' => 3,
            'captured_at' => now(),
        ]);

        SiteFirewallRule::create([
            'site_id' => $site->id,
            'provider' => 'bunny',
            'rule_type' => SiteFirewallRule::TYPE_IP,
            'target' => '203.0.113.10',
            'action' => SiteFirewallRule::ACTION_BLOCK,
            'mode' => SiteFirewallRule::MODE_ENFORCED,
            'status' => SiteFirewallRule::STATUS_ACTIVE,
        ]);

        SiteAvailabilityCheck::create([
            'site_id' => $site->id,
            'checked_at' => now(),
            'status' => 'up',
            'status_code' => 200,
        ]);

        EdgeRequestLog::create([
            'site_id' => $site->id,
            'event_at' => now(),
            'ip' => '203.0.113.10',
            'country' => 'US',
            'method' => 'GET',
            'host' => 'example.com',
            'path' => '/',
            'status_code' => 200,
            'action' => 'ALLOW',
            'rule' => 'edge',
            'meta' => ['bytes' => 123],
        ]);

        AuditLog::create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'action' => 'site.update',
            'status' => 'success',
            'message' => 'Updated',
        ]);

        $counts = app(SiteDeletionService::class)->deleteSite($site);

        $this->assertSame(1, $counts['site']);
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('alert_channels', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('alert_rules', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('alert_events', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('site_events', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('site_analytics_metrics', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('site_firewall_rules', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('site_availability_checks', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('edge_request_logs', ['site_id' => $site->id]);
        $this->assertDatabaseMissing('audit_logs', ['site_id' => $site->id]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'selected_site_id' => null,
        ]);
    }
}
