<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Site;
use App\Services\Sites\SiteRoutingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteRoutingStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_for_site_reports_protected_when_all_hostnames_point_to_edge(): void
    {
        $service = new class extends SiteRoutingStatusService
        {
            protected function resolveDns(string $domain): array
            {
                return [
                    'cname' => [$domain === 'example.com' ? 'edge.example.net' : 'edge.example.net'],
                    'a' => [],
                    'aaaa' => [],
                ];
            }

            protected function lookupByType(string $name, string $type): array
            {
                return [];
            }
        };

        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'www_enabled' => true,
            'www_domain' => 'www.example.com',
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
            'status' => Site::STATUS_ACTIVE,
            'required_dns_records' => [
                'traffic' => [
                    ['name' => 'example.com', 'value' => 'edge.example.net'],
                    ['name' => 'www.example.com', 'value' => 'edge.example.net'],
                ],
            ],
        ]);

        $status = $service->statusForSite($site, true);

        $this->assertSame('protected', $status['status']);
        $this->assertSame('Protected', $status['label']);
        $this->assertCount(2, $status['domains']);
    }

    public function test_status_for_site_reports_drift_when_hostnames_do_not_point_to_edge(): void
    {
        $service = new class extends SiteRoutingStatusService
        {
            protected function resolveDns(string $domain): array
            {
                return [
                    'cname' => ['origin.example.net'],
                    'a' => [],
                    'aaaa' => [],
                ];
            }

            protected function lookupByType(string $name, string $type): array
            {
                return [];
            }
        };

        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'www_enabled' => false,
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
            'status' => Site::STATUS_ACTIVE,
            'required_dns_records' => [
                'traffic' => [
                    ['name' => 'example.com', 'value' => 'edge.example.net'],
                ],
            ],
        ]);

        $status = $service->statusForSite($site, true);

        $this->assertSame('drift', $status['status']);
        $this->assertSame('Routing Drift Detected', $status['label']);
        $this->assertFalse($status['domains'][0]['points_to_edge']);
    }
}
