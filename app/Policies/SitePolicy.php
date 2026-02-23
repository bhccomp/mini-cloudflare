<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organizations()->exists();
    }

    public function view(User $user, Site $site): bool
    {
        return $user->organizations()->whereKey($site->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->exists();
    }

    public function update(User $user, Site $site): bool
    {
        return $this->view($user, $site);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->view($user, $site);
    }

    public function manageSecurityActions(User $user, Site $site): bool
    {
        return $this->view($user, $site);
    }
}
