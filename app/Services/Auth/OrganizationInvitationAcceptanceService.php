<?php

namespace App\Services\Auth;

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\OrganizationAccessService;

class OrganizationInvitationAcceptanceService
{
    public function accept(OrganizationInvitation $invitation, User $user): void
    {
        if (! $invitation->isPending()) {
            throw new \RuntimeException('This invitation is no longer valid.');
        }

        if (strtolower($invitation->email) !== strtolower((string) $user->email)) {
            throw new \RuntimeException('This invitation is for a different email address.');
        }

        $organization = $invitation->organization;
        $role = $invitation->role ?: OrganizationAccessService::ROLE_VIEWER;
        $permissions = app(OrganizationAccessService::class)->normalizePermissions((array) ($invitation->permissions ?? []));

        $organization->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ],
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $invitation->update([
            'accepted_by_user_id' => $user->id,
            'accepted_at' => now(),
        ]);
    }
}
