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
            'https://api.bunny.net/shield/shield-zones*' => Http::response([
                'Items' => [
                    ['Id' => 999, 'PullZoneId' => 321],
                ],
            ], 200),
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
        $this->assertSame(999, data_get($result, 'provider_meta.shield_zone_id'));
        $this->assertNull(data_get($result, 'provider_meta.edge_error_script_id'));
        $this->assertSame('inactive', data_get($result, 'provider_meta.edge_error_script_status'));

        Http::assertSentCount(9);
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
                && data_get($request->data(), 'MiddlewareScriptId') === null
                && data_get($request->data(), 'EnableLogging') === true
                && data_get($request->data(), 'LoggingSaveToStorage') === true
                && (int) data_get($request->data(), 'LoggingStorageZoneId') === 777;
        });
    }

    public function test_provision_can_enable_bunny_shield_advanced_plan_during_onboarding(): void
    {
        config()->set('edge.bunny.base_url', 'https://api.bunny.net');
        config()->set('edge.bunny.shield_auto_upgrade_to_advanced', true);
        config()->set('edge.bunny.shield_advanced_plan_type', 0);

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
            'https://api.bunny.net/shield/shield-zones*' => Http::response([
                'Items' => [
                    ['Id' => 999, 'PullZoneId' => 321],
                ],
            ], 200),
            'https://api.bunny.net/shield/shield-zone/999' => Http::response([
                'data' => [
                    'shieldZoneId' => 999,
                    'premiumPlan' => false,
                    'planType' => 0,
                    'learningMode' => true,
                    'wafEnabled' => true,
                    'wafExecutionMode' => 1,
                    'wafProfileId' => 1,
                    'wafDisabledRules' => [],
                    'wafLogOnlyRules' => [],
                    'wafRequestHeaderLoggingEnabled' => true,
                    'wafRequestIgnoredHeaders' => [],
                    'wafRealtimeThreatIntelligenceEnabled' => false,
                    'wafEngineConfig' => [],
                    'wafRequestBodyLimitAction' => 1,
                    'wafResponseBodyLimitAction' => 2,
                    'dDoSShieldSensitivity' => 0,
                    'dDoSExecutionMode' => 0,
                    'dDoSChallengeWindow' => 1800,
                    'blockVpn' => null,
                    'blockTor' => null,
                    'blockDatacentre' => null,
                    'whitelabelResponsePages' => false,
                ],
            ], 200),
            'https://api.bunny.net/shield/shield-zone' => Http::response([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->provision($site);

        $this->assertTrue($result['ok']);
        $this->assertSame('active', data_get($result, 'provider_meta.shield_plan_status'));
        $this->assertSame('advanced', data_get($site->fresh()->provider_meta, 'shield_plan'));
        $this->assertTrue((bool) data_get($site->fresh()->provider_meta, 'shield_premium_plan'));

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && $request->url() === 'https://api.bunny.net/shield/shield-zone'
                && data_get($request->data(), 'shieldZone.premiumPlan') === true
                && (int) data_get($request->data(), 'shieldZone.planType') === 0;
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
            'provider_meta' => ['shield_zone_id' => 85227],
            'status' => Site::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://api.bunny.net/pullzone' => Http::sequence()
                ->push([
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
                ], 200)
                ->push([], 200),
            'https://api.bunny.net/shield/shield-zones*' => Http::sequence()
                ->push([
                    'Items' => [
                        ['Id' => 85227, 'PullZoneId' => 321],
                    ],
                ], 200)
                ->push(['Items' => []], 200),
            'https://api.bunny.net/shield/shield-zone/85227' => Http::response([
                'data' => [
                    'shieldZoneId' => 85227,
                    'premiumPlan' => true,
                    'planType' => 1,
                    'learningMode' => true,
                    'wafEnabled' => true,
                    'wafExecutionMode' => 1,
                    'wafProfileId' => 1,
                    'wafDisabledRules' => [],
                    'wafLogOnlyRules' => [],
                    'wafRequestHeaderLoggingEnabled' => true,
                    'wafRequestIgnoredHeaders' => [],
                    'wafRealtimeThreatIntelligenceEnabled' => false,
                    'wafEngineConfig' => [],
                    'wafRequestBodyLimitAction' => 1,
                    'wafResponseBodyLimitAction' => 2,
                    'dDoSShieldSensitivity' => 0,
                    'dDoSExecutionMode' => 0,
                    'dDoSChallengeWindow' => 1800,
                    'blockVpn' => null,
                    'blockTor' => null,
                    'blockDatacentre' => null,
                    'whitelabelResponsePages' => true,
                ],
            ], 200),
            'https://api.bunny.net/shield/shield-zone' => Http::response([], 200),
            'https://api.bunny.net/pullzone/321' => Http::response([], 200),
            'https://api.bunny.net/pullzone/654' => Http::response([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->deleteDeployment($site);

        $this->assertTrue($result['changed']);
        $this->assertSame([85227], $result['downgraded_shield_zone_ids']);
        $this->assertSame([85227], $result['verified_deleted_shield_zone_ids']);
        Http::assertSentCount(8);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://api.bunny.net/pullzone');
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_starts_with($request->url(), 'https://api.bunny.net/shield/shield-zones'));
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://api.bunny.net/shield/shield-zone/85227');
        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && $request->url() === 'https://api.bunny.net/shield/shield-zone'
                && data_get($request->data(), 'shieldZone.premiumPlan') === false
                && (int) data_get($request->data(), 'shieldZone.planType') === 0;
        });
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://api.bunny.net/pullzone/321');
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://api.bunny.net/pullzone/654');
    }

    public function test_set_development_mode_updates_edge_zone_settings(): void
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
            'provider_meta' => ['zone_name' => 'fp-1-example-com'],
            'www_enabled' => true,
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
            'status' => Site::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://api.bunny.net/pullzone/321' => Http::sequence()
                ->push([
                    'Id' => 321,
                    'Name' => 'fp-1-example-com',
                    'OriginUrl' => 'http://198.51.100.9',
                    'OriginHostHeader' => 'example.com',
                ], 200)
                ->push([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->setDevelopmentMode($site, true);

        $this->assertTrue($result['changed']);
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.bunny.net/pullzone/321'
                && data_get($request->data(), 'DisableCache') === true;
        });
    }

    public function test_set_troubleshooting_mode_disables_bunny_waf_and_enables_development_mode(): void
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
            'provider_meta' => ['zone_name' => 'fp-1-example-com', 'shield_zone_id' => 85227],
            'www_enabled' => true,
            'origin_type' => 'ip',
            'origin_ip' => '198.51.100.9',
            'origin_url' => 'http://198.51.100.9',
            'status' => Site::STATUS_ACTIVE,
            'development_mode' => false,
        ]);

        Http::fake([
            'https://api.bunny.net/pullzone/321' => Http::sequence()
                ->push([
                    'Id' => 321,
                    'Name' => 'fp-1-example-com',
                    'OriginUrl' => 'http://198.51.100.9',
                    'OriginHostHeader' => 'example.com',
                ], 200)
                ->push([], 200),
            'https://api.bunny.net/shield/shield-zone/85227' => Http::sequence()
                ->push([
                    'data' => [
                        'shieldZoneId' => 85227,
                        'wafEnabled' => true,
                        'premiumPlan' => false,
                        'planType' => 0,
                        'learningMode' => true,
                        'wafExecutionMode' => 1,
                        'wafProfileId' => 1,
                        'wafDisabledRules' => [],
                        'wafLogOnlyRules' => [],
                        'wafRequestHeaderLoggingEnabled' => true,
                        'wafRequestIgnoredHeaders' => [],
                        'wafRealtimeThreatIntelligenceEnabled' => false,
                        'wafEngineConfig' => [],
                        'wafRequestBodyLimitAction' => 1,
                        'wafResponseBodyLimitAction' => 2,
                        'dDoSShieldSensitivity' => 0,
                        'dDoSExecutionMode' => 0,
                        'dDoSChallengeWindow' => 1800,
                        'blockVpn' => null,
                        'blockTor' => null,
                        'blockDatacentre' => null,
                        'whitelabelResponsePages' => false,
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'shieldZoneId' => 85227,
                        'wafEnabled' => true,
                        'premiumPlan' => false,
                        'planType' => 0,
                        'learningMode' => true,
                        'wafExecutionMode' => 1,
                        'wafProfileId' => 1,
                        'wafDisabledRules' => [],
                        'wafLogOnlyRules' => [],
                        'wafRequestHeaderLoggingEnabled' => true,
                        'wafRequestIgnoredHeaders' => [],
                        'wafRealtimeThreatIntelligenceEnabled' => false,
                        'wafEngineConfig' => [],
                        'wafRequestBodyLimitAction' => 1,
                        'wafResponseBodyLimitAction' => 2,
                        'dDoSShieldSensitivity' => 0,
                        'dDoSExecutionMode' => 0,
                        'dDoSChallengeWindow' => 1800,
                        'blockVpn' => null,
                        'blockTor' => null,
                        'blockDatacentre' => null,
                        'whitelabelResponsePages' => false,
                    ],
                ], 200),
            'https://api.bunny.net/shield/shield-zone' => Http::response([], 200),
        ]);

        $provider = new BunnyCdnProvider;
        $result = $provider->setTroubleshootingMode($site, true);

        $this->assertTrue($result['changed']);
        $this->assertTrue($result['development_mode']);
        $this->assertFalse($result['waf_enabled']);
        $this->assertSame(false, data_get($site->fresh()->provider_meta, 'troubleshooting_snapshot.development_mode'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone/321'
            && data_get($request->data(), 'DisableCache') === true);

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && $request->url() === 'https://api.bunny.net/shield/shield-zone'
            && data_get($request->data(), 'shieldZone.wafEnabled') === false);
    }
}
