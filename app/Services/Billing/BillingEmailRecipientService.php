<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Services\OrganizationAccessService;

class BillingEmailRecipientService
{
    /**
     * @return array<int, string>
     */
    public function forOrganization(Organization $organization): array
    {
        $emails = [];

        if ($organization->billing_email) {
            $emails[] = strtolower(trim((string) $organization->billing_email));
        }

        $memberEmails = $organization->users()
            ->whereIn('organization_user.role', [
                OrganizationAccessService::ROLE_OWNER,
                OrganizationAccessService::ROLE_ADMIN,
            ])
            ->pluck('users.email')
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->all();

        return array_values(array_unique(array_filter(array_merge($emails, $memberEmails))));
    }
}
