<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Site;
use App\Services\Aws\AwsAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteAnalyticsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_sync_persists_site_analytics_metrics(): void
    {
        config()->set('services.aws_edge.dry_run', true);

        $org = Organization::create(['name' => 'Org Metrics', 'slug' => 'org-metrics']);

        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'Metrics Site',
            'name' => 'Metrics Site',
            'apex_domain' => 'metrics-example.com',
            'origin_type' => 'url',
            'origin_url' => 'https://origin.metrics-example.com',
            'status' => Site::STATUS_ACTIVE,
            'cloudfront_distribution_id' => 'E123456789ABC',
            'cloudfront_domain_name' => 'd111111abcdef8.cloudfront.net',
        ]);

        $snapshot = app(AwsAnalyticsService::class)->syncSiteMetrics($site);

        $this->assertNotNull($snapshot);
        $this->assertSame($site->id, $snapshot->site_id);
        $this->assertNotNull($snapshot->blocked_requests_24h);
        $this->assertNotNull($snapshot->cache_hit_ratio);
        $this->assertNotEmpty($snapshot->trend_labels);
        $this->assertNotEmpty($snapshot->blocked_trend);
        $this->assertNotEmpty($snapshot->allowed_trend);
    }
}
