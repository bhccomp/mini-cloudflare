<?php

namespace App\Services\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceAccountCreator
{
    public function create(
        string $name,
        string $email,
        string $password,
        ?string $organizationName = null,
        ?string $googleId = null,
        ?string $avatarUrl = null,
        bool $markEmailVerified = false,
    ): User {
        $normalizedEmail = Str::lower(trim($email));
        $organizationName = $this->normalizeOrganizationName($organizationName, $name);

        return DB::transaction(function () use ($name, $normalizedEmail, $password, $organizationName, $googleId, $avatarUrl, $markEmailVerified): User {
            $organization = Organization::create([
                'name' => $organizationName,
                'slug' => $this->generateUniqueOrganizationSlug($organizationName),
                'billing_email' => $normalizedEmail,
            ]);

            $user = User::create([
                'name' => $name,
                'email' => $normalizedEmail,
                'password' => $password,
                'current_organization_id' => $organization->id,
                'google_id' => $googleId,
                'avatar_url' => $avatarUrl,
                'email_verified_at' => $markEmailVerified ? now() : null,
            ]);

            $organization->users()->attach($user->id, [
                'role' => OrganizationAccessService::ROLE_OWNER,
                'permissions' => json_encode(
                    app(OrganizationAccessService::class)->defaultPermissionsForRole(OrganizationAccessService::ROLE_OWNER),
                    JSON_THROW_ON_ERROR,
                ),
            ]);

            return $user;
        });
    }

    private function normalizeOrganizationName(?string $organizationName, string $name): string
    {
        $organizationName = trim((string) $organizationName);

        if ($organizationName !== '') {
            return $organizationName;
        }

        $firstName = trim((string) Str::of($name)->before(' '));

        return $firstName !== '' ? "{$firstName}'s Workspace" : 'My Workspace';
    }

    private function generateUniqueOrganizationSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'workspace';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
