<?php

namespace Tests\Feature;

use App\Livewire\Filament\App\UiModeSwitcher;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\UiModeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UiModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mode_is_simple_for_new_users(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $user = User::factory()->create([
            'is_super_admin' => false,
            'current_organization_id' => $organization->id,
        ]);
        $user->organizations()->attach($organization->id, ['role' => 'owner']);

        $this->actingAs($user);

        $mode = app(UiModeManager::class)->current($user);

        $this->assertSame(UiModeManager::SIMPLE, $mode);
    }

    public function test_switching_mode_updates_db_and_session(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $user = User::factory()->create([
            'is_super_admin' => false,
            'current_organization_id' => $organization->id,
        ]);
        $user->organizations()->attach($organization->id, ['role' => 'owner']);

        Livewire::actingAs($user)
            ->test(UiModeSwitcher::class)
            ->call('setMode', UiModeManager::PRO);

        $this->assertSame(UiModeManager::PRO, $user->fresh()->ui_mode);
        $this->assertSame(UiModeManager::PRO, session((string) config('ui.session_key')));
    }

    public function test_pro_only_sections_hidden_in_simple_mode(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $user = User::factory()->create([
            'is_super_admin' => false,
            'current_organization_id' => $organization->id,
            'ui_mode' => UiModeManager::SIMPLE,
        ]);
        $user->organizations()->attach($organization->id, ['role' => 'owner']);

        $site = Site::create([
            'organization_id' => $organization->id,
            'display_name' => 'Main Site',
            'name' => 'Main Site',
            'apex_domain' => 'example.com',
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => Site::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/app/firewall?site_id='.$site->id)
            ->assertOk()
            ->assertSee('Switch to Pro mode')
            ->assertDontSee('Top Countries');

        app(UiModeManager::class)->setMode($user->fresh(), UiModeManager::PRO);

        $this->get('/app/firewall?site_id='.$site->id)
            ->assertOk()
            ->assertDontSee('Simple Firewall View')
            ->assertDontSee('Switch to Pro mode');
    }
}
