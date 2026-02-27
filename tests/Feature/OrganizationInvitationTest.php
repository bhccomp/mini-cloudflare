<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_accept_valid_invitation_for_matching_email(): void
    {
        $organization = Organization::create(['name' => 'Org Invite', 'slug' => 'org-invite']);
        $inviter = User::factory()->create(['email' => 'owner@example.com']);
        $invitee = User::factory()->create(['email' => 'member@example.com']);

        $organization->users()->attach($inviter->id, [
            'role' => OrganizationAccessService::ROLE_OWNER,
        ]);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $inviter->id,
            'email' => $invitee->email,
            'role' => OrganizationAccessService::ROLE_EDITOR,
            'permissions' => [
                OrganizationAccessService::PERMISSION_SITES_READ => true,
                OrganizationAccessService::PERMISSION_SITES_WRITE => true,
            ],
            'token' => hash('sha256', 'invite-token'),
            'expires_at' => now()->addDay(),
        ]);

        $response = $this
            ->actingAs($invitee)
            ->get(route('app.invitations.accept', ['token' => $invitation->token]));

        $response->assertRedirect('/app/organization-settings');

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $invitee->id,
            'role' => OrganizationAccessService::ROLE_EDITOR,
        ]);

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'accepted_by_user_id' => $invitee->id,
        ]);
    }

    public function test_user_cannot_accept_invitation_for_different_email(): void
    {
        $organization = Organization::create(['name' => 'Org Invite B', 'slug' => 'org-invite-b']);
        $inviter = User::factory()->create(['email' => 'owner-b@example.com']);
        $invitee = User::factory()->create(['email' => 'wrong-user@example.com']);

        $organization->users()->attach($inviter->id, [
            'role' => OrganizationAccessService::ROLE_OWNER,
        ]);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $inviter->id,
            'email' => 'another-user@example.com',
            'role' => OrganizationAccessService::ROLE_VIEWER,
            'permissions' => [
                OrganizationAccessService::PERMISSION_SITES_READ => true,
            ],
            'token' => hash('sha256', 'invite-token-b'),
            'expires_at' => now()->addDay(),
        ]);

        $response = $this
            ->actingAs($invitee)
            ->get(route('app.invitations.accept', ['token' => $invitation->token]));

        $response->assertRedirect('/app');

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $invitee->id,
        ]);

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'accepted_by_user_id' => null,
        ]);
    }
}
