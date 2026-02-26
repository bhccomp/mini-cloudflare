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

    public function test_provision_creates_pull_zone_and_returns_dns_records(): void
    {
        config()->set('edge.bunny.base_url', 'https://api.bunny.net');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'bunny'],
            ['value' => ['api_key' => 'test-key', 'logging_storage_zone_id' => 777], 'is_encrypted' => false]
        );

        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'www_enabled' => true,
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
            'status' => Site::STATUS_DEPLOYING,
        ]);

        Http::fake([
            'http://198.51.100.9/*' => Http::response('', 301, ['Location' => 'https://example.com/']),
            'https://198.51.100.9/*' => Http::response('', 200),
            'https://api.bunny.net/pullzone' => Http::response(['Id' => 321, 'Name' => 'fp-1-example-com'], 201),
            'https://api.bunny.net/pullzone/321' => Http::response([], 200),
            'https://api.bunny.net/pullzone/321/addHostname' => Http::response([], 200),
            'https://api.bunny.net/pullzone/loadFreeCertificate*' => Http::response([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->provision($site);

        $this->assertTrue($result['ok']);
        $this->assertSame(Site::PROVIDER_BUNNY, $result['provider']);
        $this->assertSame('321', $result['provider_resource_id']);
        $this->assertSame('321', $result['distribution_id']);
        $this->assertSame('fp-1-example-com.b-cdn.net', $result['distribution_domain_name']);
        $this->assertNotEmpty($result['dns_records']);
        $this->assertSame('https://198.51.100.9', data_get($result, 'provider_meta.origin_url'));
        $this->assertSame('example.com', data_get($result, 'provider_meta.origin_host_header'));

        Http::assertSentCount(8);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.bunny.net/pullzone'
                && data_get($request->data(), 'OriginUrl') === 'https://198.51.100.9'
                && data_get($request->data(), 'OriginHostHeader') === 'example.com'
                && data_get($request->data(), 'AddHostHeader') === true
                && data_get($request->data(), 'EnableLogging') === true
                && data_get($request->data(), 'LoggingSaveToStorage') === true
                && (int) data_get($request->data(), 'LoggingStorageZoneId') === 777;
        });
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.bunny.net/pullzone/loadFreeCertificate'));
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.bunny.net/pullzone/321'
                && data_get($request->data(), 'OriginUrl') === 'https://198.51.100.9'
                && data_get($request->data(), 'OriginHostHeader') === 'example.com'
                && data_get($request->data(), 'EnableLogging') === true
                && data_get($request->data(), 'LoggingSaveToStorage') === true
                && (int) data_get($request->data(), 'LoggingStorageZoneId') === 777;
        });
    }

    public function test_purge_cache_calls_bunny_purge_endpoint(): void
    {
        config()->set('edge.bunny.base_url', 'https://api.bunny.net');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'bunny'],
            ['value' => ['api_key' => 'test-key'], 'is_encrypted' => false]
        );

        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'provider_resource_id' => '321',
            'www_enabled' => true,
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
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

    public function test_delete_deployment_calls_bunny_delete_pull_zone_endpoint(): void
    {
        config()->set('edge.bunny.base_url', 'https://api.bunny.net');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'bunny'],
            ['value' => ['api_key' => 'test-key'], 'is_encrypted' => false]
        );

        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_BUNNY,
            'provider_resource_id' => '321',
            'www_enabled' => true,
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
            'status' => Site::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://api.bunny.net/pullzone' => Http::response([
                [
                    'Id' => 321,
                    'Name' => 'fp-1-example-com',
                    'Hostnames' => [
                        ['Value' => 'example.com'],
                        ['Value' => 'www.example.com'],
                    ],
                ],
                [
                    'Id' => 654,
                    'Name' => 'fp-1-example-com-old',
                    'Hostnames' => [
                        ['Value' => 'example.com'],
                    ],
                ],
            ], 200),
            'https://api.bunny.net/pullzone/321' => Http::response([], 200),
            'https://api.bunny.net/pullzone/654' => Http::response([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->deleteDeployment($site);

        $this->assertTrue($result['changed']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://api.bunny.net/pullzone');
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://api.bunny.net/pullzone/321');
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://api.bunny.net/pullzone/654');
    }
}
