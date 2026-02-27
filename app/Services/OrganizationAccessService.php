<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

class OrganizationAccessService
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_VIEWER = 'viewer';

    public const PERMISSION_SITES_READ = 'sites_read';

    public const PERMISSION_SITES_WRITE = 'sites_write';

    public const PERMISSION_ALERTS_READ = 'alerts_read';

    public const PERMISSION_ALERTS_WRITE = 'alerts_write';

    public const PERMISSION_LOGS_READ = 'logs_read';

    public const PERMISSION_MEMBERS_MANAGE = 'members_manage';

    public const PERMISSION_SETTINGS_MANAGE = 'settings_manage';

    public const PERMISSION_BILLING_READ = 'billing_read';

    /**
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        return [
            self::ROLE_VIEWER => 'Viewer (Read only)',
            self::ROLE_EDITOR => 'Editor (Write)',
            self::ROLE_ADMIN => 'Admin (Team management)',
            self::ROLE_OWNER => 'Owner',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function defaultPermissionsForRole(string $role): array
    {
        return match ($role) {
            self::ROLE_OWNER => [
                self::PERMISSION_SITES_READ => true,
                self::PERMISSION_SITES_WRITE => true,
                self::PERMISSION_ALERTS_READ => true,
                self::PERMISSION_ALERTS_WRITE => true,
                self::PERMISSION_LOGS_READ => true,
                self::PERMISSION_MEMBERS_MANAGE => true,
                self::PERMISSION_SETTINGS_MANAGE => true,
                self::PERMISSION_BILLING_READ => true,
            ],
            self::ROLE_ADMIN => [
                self::PERMISSION_SITES_READ => true,
                self::PERMISSION_SITES_WRITE => true,
                self::PERMISSION_ALERTS_READ => true,
                self::PERMISSION_ALERTS_WRITE => true,
                self::PERMISSION_LOGS_READ => true,
                self::PERMISSION_MEMBERS_MANAGE => true,
                self::PERMISSION_SETTINGS_MANAGE => true,
                self::PERMISSION_BILLING_READ => true,
            ],
            self::ROLE_EDITOR => [
                self::PERMISSION_SITES_READ => true,
                self::PERMISSION_SITES_WRITE => true,
                self::PERMISSION_ALERTS_READ => true,
                self::PERMISSION_ALERTS_WRITE => true,
                self::PERMISSION_LOGS_READ => true,
                self::PERMISSION_MEMBERS_MANAGE => false,
                self::PERMISSION_SETTINGS_MANAGE => false,
                self::PERMISSION_BILLING_READ => false,
            ],
            default => [
                self::PERMISSION_SITES_READ => true,
                self::PERMISSION_SITES_WRITE => false,
                self::PERMISSION_ALERTS_READ => true,
                self::PERMISSION_ALERTS_WRITE => false,
                self::PERMISSION_LOGS_READ => true,
                self::PERMISSION_MEMBERS_MANAGE => false,
                self::PERMISSION_SETTINGS_MANAGE => false,
                self::PERMISSION_BILLING_READ => false,
            ],
        };
    }

    public function currentOrganization(?User $user): ?Organization
    {
        if (! $user) {
            return null;
        }

        $organizationId = $user->current_organization_id;

        if ($organizationId && $user->organizations()->whereKey($organizationId)->exists()) {
            return Organization::query()->find($organizationId);
        }

        $organizationId = $user->organizations()->value('organizations.id');

        if (! $organizationId) {
            return null;
        }

        return Organization::query()->find($organizationId);
    }

    /**
     * @return array{role: string|null, permissions: array<string, bool>}
     */
    public function membership(User $user, Organization $organization): array
    {
        $member = $organization->users()
            ->where('users.id', $user->id)
            ->first(['users.id']);

        if (! $member) {
            return ['role' => null, 'permissions' => []];
        }

        $role = (string) ($member->pivot?->role ?? self::ROLE_VIEWER);
        $customPermissions = $member->pivot?->permissions;
        if (is_string($customPermissions)) {
            $decoded = json_decode($customPermissions, true);
            $custom = is_array($decoded) ? $decoded : [];
        } else {
            $custom = is_array($customPermissions) ? $customPermissions : [];
        }

        return [
            'role' => $role,
            'permissions' => array_merge(
                $this->defaultPermissionsForRole($role),
                $this->normalizePermissions($custom, false),
            ),
        ];
    }

    public function can(User $user, Organization $organization, string $permission): bool
    {
        $membership = $this->membership($user, $organization);

        if (! $membership['role']) {
            return false;
        }

        return (bool) ($membership['permissions'][$permission] ?? false);
    }

    public function canForOrganizationId(User $user, int $organizationId, string $permission): bool
    {
        $organization = Organization::query()->find($organizationId);

        if (! $organization) {
            return false;
        }

        return $this->can($user, $organization, $permission);
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @return array<string, bool>
     */
    public function normalizePermissions(array $permissions, bool $fillMissing = true): array
    {
        $validKeys = array_keys($this->defaultPermissionsForRole(self::ROLE_VIEWER));
        $normalized = collect($permissions)
            ->filter(fn (mixed $value, mixed $key): bool => in_array((string) $key, $validKeys, true))
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [(string) $key => (bool) $value])
            ->all();

        if (! $fillMissing) {
            return $normalized;
        }

        return collect($validKeys)
            ->mapWithKeys(fn (string $key): array => [$key => (bool) ($normalized[$key] ?? false)])
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    public function members(Organization $organization): Collection
    {
        return $organization->users()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);
    }
}
