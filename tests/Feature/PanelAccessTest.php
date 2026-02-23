<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_accesses_only_admin_panel(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $adminPanel = Mockery::mock(Panel::class);
        $adminPanel->shouldReceive('getId')->andReturn('admin');

        $userPanel = Mockery::mock(Panel::class);
        $userPanel->shouldReceive('getId')->andReturn('app');

        $this->assertTrue($admin->canAccessPanel($adminPanel));
        $this->assertFalse($admin->canAccessPanel($userPanel));
    }

    public function test_customer_accesses_only_user_panel_with_membership(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $user = User::factory()->create(['is_super_admin' => false]);
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $adminPanel = Mockery::mock(Panel::class);
        $adminPanel->shouldReceive('getId')->andReturn('admin');

        $userPanel = Mockery::mock(Panel::class);
        $userPanel->shouldReceive('getId')->andReturn('app');

        $this->assertFalse($user->canAccessPanel($adminPanel));
        $this->assertTrue($user->canAccessPanel($userPanel));
    }
}
