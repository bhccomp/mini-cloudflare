<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_start_free_call_to_action(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '87.116.135.34',
        ])->get('/');

        $response->assertOk();
        $response->assertSee('Start Free');
        $response->assertSee('Login');
        $response->assertDontSee('Logout');
    }

    public function test_regular_user_sees_dashboard_call_to_action_for_app_panel(): void
    {
        $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $user = User::factory()->create(['is_super_admin' => false]);
        $organization->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertDontSee('Start Free');
        $response->assertSee('Logout');
        $response->assertDontSee('Login');
    }

    public function test_admin_sees_dashboard_call_to_action_for_admin_panel(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertDontSee('Start Free');
        $response->assertSee('Logout');
        $response->assertDontSee('Login');
    }
}
