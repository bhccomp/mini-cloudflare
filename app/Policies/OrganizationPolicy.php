<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

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
        return $this->isOwner($user, $organization);
    }

    protected function isOwner(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }
}
