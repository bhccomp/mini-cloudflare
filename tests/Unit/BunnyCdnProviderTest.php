<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Services\Edge\Providers\BunnyCdnProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BunnyCdnProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_deployment_creates_pull_zone_and_returns_dns_records(): void
    {
        config()->set('edge.bunny.base_url', 'https://api.bunny.net');

        SystemSetting::create([
            'key' => 'bunny',
            'value' => ['api_key' => 'test-key'],
            'is_encrypted' => false,
        ]);

        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'www_enabled' => true,
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => Site::STATUS_DEPLOYING,
        ]);

        Http::fake([
            'https://api.bunny.net/pullzone' => Http::response(['Id' => 321, 'Name' => 'fp-1-example-com'], 201),
            'https://api.bunny.net/pullzone/321/addHostName' => Http::response([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->createDeployment($site);

        $this->assertTrue($result['ok']);
        $this->assertSame(Site::PROVIDER_BUNNY, $result['provider']);
        $this->assertSame('321', $result['provider_resource_id']);
        $this->assertSame('321', $result['distribution_id']);
        $this->assertSame('fp-1-example-com.b-cdn.net', $result['distribution_domain_name']);
        $this->assertNotEmpty($result['dns_records']);

        Http::assertSentCount(3);
    }

    public function test_purge_cache_calls_bunny_purge_endpoint(): void
    {
        config()->set('edge.bunny.base_url', 'https://api.bunny.net');

        SystemSetting::create([
            'key' => 'bunny',
            'value' => ['api_key' => 'test-key'],
            'is_encrypted' => false,
        ]);

        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'provider_resource_id' => '321',
            'www_enabled' => true,
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => Site::STATUS_DEPLOYING,
        ]);

        Http::fake([
            'https://api.bunny.net/pullzone/321/purgeCache' => Http::response([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->purgeCache($site, ['/*']);

        $this->assertTrue($result['changed']);
        Http::assertSentCount(1);
    }
}
