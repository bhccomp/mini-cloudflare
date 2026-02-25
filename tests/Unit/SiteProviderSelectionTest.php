<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteProviderSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_defaults_to_bunny_provider_from_config(): void
    {
        config()->set('edge.default_provider', Site::PROVIDER_BUNNY);

        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);

        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'example.com',
            'name' => 'example.com',
            'apex_domain' => 'example.com',
            'www_enabled' => true,
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => Site::STATUS_DRAFT,
        ]);

        $this->assertSame(Site::PROVIDER_BUNNY, $site->provider);
        $this->assertSame(Site::ONBOARDING_DRAFT, $site->onboarding_status);
    }
}
