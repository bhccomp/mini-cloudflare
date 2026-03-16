<?php

namespace Tests\Feature;

use App\Models\EarlyAccessLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarlyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_homepage_to_early_access(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '1.2.3.4',
        ])->get('/');

        $response->assertRedirect(route('early-access'));
    }

    public function test_bypass_ip_can_still_view_homepage(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '87.116.135.34',
        ])->get('/');

        $response->assertOk();
        $response->assertSee('Stop Attacks Before They Reach Your WordPress Server');
    }

    public function test_authenticated_user_can_still_view_homepage(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Stop Attacks Before They Reach Your WordPress Server');
    }

    public function test_guest_can_submit_early_access_form(): void
    {
        $this->get(route('early-access'));

        $response = $this->from(route('early-access'))->post(route('early-access.store'), [
            '_token' => session()->token(),
            'name' => 'Jane Founder',
            'email' => 'jane@example.com',
            'company_name' => 'Acme Media',
            'website_url' => 'https://acme.example',
            'monthly_requests_band' => '500k to 5M requests',
            'websites_managed' => '6-20 websites',
            'notes' => 'Interested in launch pricing.',
            'wants_launch_discount' => '1',
        ]);

        $response->assertRedirect(route('early-access'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('early_access_leads', [
            'email' => 'jane@example.com',
            'name' => 'Jane Founder',
            'company_name' => 'Acme Media',
            'monthly_requests_band' => '500k to 5M requests',
            'websites_managed' => '6-20 websites',
            'wants_launch_discount' => true,
        ]);
    }
}
