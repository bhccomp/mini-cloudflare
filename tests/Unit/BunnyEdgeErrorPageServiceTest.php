<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\Bunny\BunnyEdgeErrorPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BunnyEdgeErrorPageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compiled_templates_include_domain_placeholder_and_marketing_copy(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'bunny'],
            ['value' => ['api_key' => 'test-key'], 'is_encrypted' => false]
        );

        $service = app(BunnyEdgeErrorPageService::class);
        $templates = $service->compiledTemplates();

        $this->assertArrayHasKey('404', $templates);
        $this->assertArrayHasKey('403', $templates);
        $this->assertArrayHasKey('429', $templates);
        $this->assertArrayHasKey('5xx', $templates);
        $this->assertStringContainsString('__FIREPHAGE_DOMAIN__', $templates['404']);
        $this->assertStringContainsString('FirePhage edge fallback', $templates['404']);
        $this->assertStringContainsString('Status __FIREPHAGE_STATUS__', $templates['5xx']);
    }

    public function test_middleware_source_contains_status_branching_and_host_injection(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'bunny'],
            ['value' => ['api_key' => 'test-key'], 'is_encrypted' => false]
        );

        $source = app(BunnyEdgeErrorPageService::class)->buildMiddlewareSource();

        $this->assertStringContainsString('addEventListener("onOriginResponse"', $source);
        $this->assertStringContainsString('request.url', $source);
        $this->assertStringContainsString('status === 404', $source);
        $this->assertStringContainsString('status === 429', $source);
        $this->assertStringContainsString('500, 502, 503, 504', $source);
    }
}
