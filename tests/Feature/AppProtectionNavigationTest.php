<?php

namespace Tests\Feature;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Filament\App\Resources\SiteResource\Pages\CreateSite;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppProtectionNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_sites_can_open_protection_pages(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org']);

        $user = User::factory()->create([
            'is_super_admin' => false,
            'current_organization_id' => $org->id,
        ]);
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $this->actingAs($user);

        foreach (['/app/status-hub', '/app/overview', '/app/ssl', '/app/cdn', '/app/cache', '/app/firewall', '/app/origin'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('No sites connected to this account yet');
        }
    }

    public function test_selected_site_context_is_visible_across_protection_pages(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org']);

        $user = User::factory()->create([
            'is_super_admin' => false,
            'current_organization_id' => $org->id,
        ]);
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'Main Site',
            'name' => 'Main Site',
            'apex_domain' => 'example.com',
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get('/app/overview?site_id='.$site->id)
            ->assertOk()
            ->assertSee('example.com');

        foreach (['/app/status-hub', '/app/ssl', '/app/cdn', '/app/cache', '/app/firewall', '/app/origin'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('example.com');
        }
    }

    public function test_site_creation_redirects_to_status_hub_and_saves_selection(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org']);

        $user = User::factory()->create([
            'is_super_admin' => false,
            'current_organization_id' => $org->id,
        ]);
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $component = Livewire::actingAs($user)
            ->test(CreateSite::class)
            ->set('data.organization_id', $org->id)
            ->set('data.apex_domain', 'wizard-example.com')
            ->set('data.origin_ip', '203.0.113.10')
            ->call('create')
            ->assertHasNoErrors();

        $site = Site::query()->where('apex_domain', 'wizard-example.com')->firstOrFail();

        $component->assertRedirect(SiteStatusHubPage::getUrl(['site_id' => $site->id]));

        $this->assertSame($site->id, $user->fresh()->selected_site_id);
        $this->assertSame('203.0.113.10', $site->origin_ip);
        $this->assertSame('http://203.0.113.10', $site->origin_url);
        $this->assertTrue($site->www_enabled);
    }
}
