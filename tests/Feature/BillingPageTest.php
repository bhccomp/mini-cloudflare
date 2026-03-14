<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_billing_page_without_subscription(): void
    {
        $organization = Organization::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'billing_email' => 'billing@example.com',
        ]);

        $user = User::factory()->create();

        $organization->users()->attach($user->id, [
            'role' => OrganizationAccessService::ROLE_OWNER,
            'permissions' => json_encode(
                app(OrganizationAccessService::class)->defaultPermissionsForRole(OrganizationAccessService::ROLE_OWNER),
                JSON_THROW_ON_ERROR,
            ),
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $response = $this->actingAs($user)->get('/app/billing');

        $response->assertOk();
        $response->assertSee('Billing Profile');
        $response->assertSee('No active plan yet');
        $response->assertSee('No invoices yet');
    }
}
