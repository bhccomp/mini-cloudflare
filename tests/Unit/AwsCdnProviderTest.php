<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use App\Services\Edge\Providers\AwsCdnProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AwsCdnProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_wraps_aws_logic_and_returns_unified_payload(): void
    {
        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_AWS,
            'www_enabled' => true,
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => Site::STATUS_DEPLOYING,
            'acm_certificate_arn' => 'arn:aws:acm:us-east-1:000000000000:certificate/demo',
        ]);

        $aws = Mockery::mock(AwsEdgeService::class);
        $aws->shouldReceive('provisionWafWebAcl')
            ->once()
            ->andReturn([
                'web_acl_arn' => 'arn:aws:wafv2:us-east-1:000000000000:global/webacl/site/123',
                'message' => 'WAF done',
            ]);

        $aws->shouldReceive('provisionCloudFrontDistribution')
            ->once()
            ->andReturn([
                'distribution_id' => 'E12345',
                'distribution_domain_name' => 'd111111abcdef8.cloudfront.net',
                'required_dns_records' => [
                    'traffic' => [[
                        'type' => 'CNAME',
                        'name' => 'www.example.com',
                        'value' => 'd111111abcdef8.cloudfront.net',
                    ]],
                ],
                'message' => 'CDN done',
            ]);

        $aws->shouldReceive('associateWebAclToDistribution')
            ->once()
            ->andReturn([
                'changed' => true,
                'message' => 'Associated',
            ]);

        $provider = new AwsCdnProvider($aws);
        $result = $provider->provision($site);

        $site->refresh();

        $this->assertTrue($result['ok']);
        $this->assertSame(Site::PROVIDER_AWS, $result['provider']);
        $this->assertSame('E12345', $result['provider_resource_id']);
        $this->assertSame('E12345', $site->cloudfront_distribution_id);
        $this->assertSame('d111111abcdef8.cloudfront.net', $site->cloudfront_domain_name);
        $this->assertSame('arn:aws:wafv2:us-east-1:000000000000:global/webacl/site/123', $site->waf_web_acl_arn);
    }
}
