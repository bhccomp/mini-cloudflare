<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use App\Services\OrganizationAccessService;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        $organization = app(OrganizationAccessService::class)->currentOrganization($user);

        if (! $organization) {
            return false;
        }

        return app(OrganizationAccessService::class)->can($user, $organization, OrganizationAccessService::PERMISSION_SITES_READ);
    }

    public function view(User $user, Site $site): bool
    {
        return app(OrganizationAccessService::class)->canForOrganizationId($user, (int) $site->organization_id, OrganizationAccessService::PERMISSION_SITES_READ);
    }

    public function create(User $user): bool
    {
        $organization = app(OrganizationAccessService::class)->currentOrganization($user);

        if (! $organization) {
            return false;
        }

        return app(OrganizationAccessService::class)->can($user, $organization, OrganizationAccessService::PERMISSION_SITES_WRITE);
    }

    public function update(User $user, Site $site): bool
    {
        return app(OrganizationAccessService::class)->canForOrganizationId($user, (int) $site->organization_id, OrganizationAccessService::PERMISSION_SITES_WRITE);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }

    public function manageSecurityActions(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }
}
