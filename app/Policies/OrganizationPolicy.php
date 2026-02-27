<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationAccessService;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organizations()->exists();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->organizations()->whereKey($organization->id)->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        return app(OrganizationAccessService::class)->can($user, $organization, OrganizationAccessService::PERMISSION_SETTINGS_MANAGE);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return app(OrganizationAccessService::class)->can($user, $organization, OrganizationAccessService::PERMISSION_MEMBERS_MANAGE);
    }

    protected function isOwner(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }
}
