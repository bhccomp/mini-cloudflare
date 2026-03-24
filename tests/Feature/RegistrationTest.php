<?php

namespace Tests\Feature;

use App\Services\OrganizationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_registration_page(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Create your FirePhage account');
    }

    public function test_guest_can_register_and_get_workspace_owner_access(): void
    {
        $this->get(route('register'));

        $response = $this->post(route('register.store'), [
            '_token' => session()->token(),
            'name' => 'Jane Founder',
            'organization_name' => 'Acme Media',
            'email' => 'Jane@Example.com',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ]);

        $response->assertRedirect('/app');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Founder',
            'email' => 'jane@example.com',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Acme Media',
            'slug' => 'acme-media',
            'billing_email' => 'jane@example.com',
        ]);

        $this->assertDatabaseHas('organization_user', [
            'role' => OrganizationAccessService::ROLE_OWNER,
        ]);

        $user = auth()->user();

        $this->assertNotNull($user);
        $this->assertNotNull($user->current_organization_id);
    }

    public function test_registration_ignores_stale_intended_admin_redirect(): void
    {
        $this->get(route('register'));

        $this->withSession([
            'url.intended' => '/admin',
        ]);

        $response = $this->post(route('register.store'), [
            '_token' => session()->token(),
            'name' => 'Jane Founder',
            'organization_name' => 'Acme Media',
            'email' => 'jane@example.com',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ]);

        $response->assertRedirect('/app');
    }
}
