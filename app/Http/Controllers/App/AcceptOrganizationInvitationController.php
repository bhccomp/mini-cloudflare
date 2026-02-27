<?php

namespace App\Http\Controllers\App;

use App\Models\OrganizationInvitation;
use App\Services\OrganizationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptOrganizationInvitationController
{
    public function __invoke(string $token, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/app/login');
        }

        $invitation = OrganizationInvitation::query()
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isPending()) {
            return redirect('/app')->with('status', 'This invitation is no longer valid.');
        }

        if (strtolower($invitation->email) !== strtolower((string) $user->email)) {
            return redirect('/app')->with('status', 'This invitation is for a different email address.');
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

        if (! $user->current_organization_id) {
            $user->forceFill(['current_organization_id' => $organization->id])->save();
        }

        $invitation->update([
            'accepted_by_user_id' => $user->id,
            'accepted_at' => now(),
        ]);

        return redirect('/app/organization-settings')
            ->with('status', 'Invitation accepted. You now have access to this organization.');
    }
}
