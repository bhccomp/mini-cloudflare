<?php

namespace App\Filament\App\Pages;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Services\OrganizationAccessService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

class OrganizationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?string $title = 'Organization Settings';

    protected string $view = 'filament.app.pages.organization-settings-page';

    public ?array $data = [];

    public ?Organization $organization = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->organization = app(OrganizationAccessService::class)->currentOrganization($user);

        abort_unless($this->organization !== null, 403);

        $this->form->fill([
            'invite_role' => OrganizationAccessService::ROLE_VIEWER,
            'invite_email' => '',
            'can_manage_members' => false,
            'can_manage_settings' => false,
            'can_billing_read' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('invite_email')
                    ->label('Invite by email')
                    ->email()
                    ->required(),
                Select::make('invite_role')
                    ->label('Permission level')
                    ->options([
                        OrganizationAccessService::ROLE_VIEWER => 'Read only',
                        OrganizationAccessService::ROLE_EDITOR => 'Write',
                        OrganizationAccessService::ROLE_ADMIN => 'Admin',
                    ])
                    ->required()
                    ->default(OrganizationAccessService::ROLE_VIEWER),
                Toggle::make('can_manage_members')
                    ->label('Can manage members')
                    ->helperText('Allow inviting/removing members and changing team roles.')
                    ->default(false),
                Toggle::make('can_manage_settings')
                    ->label('Can manage organization settings')
                    ->default(false),
                Toggle::make('can_billing_read')
                    ->label('Can view billing')
                    ->default(false),
            ])
            ->statePath('data');
    }

    public function sendInvite(): void
    {
        $user = auth()->user();
        if (! $user || ! $this->organization) {
            return;
        }

        abort_unless($this->canManageMembers(), 403);

        $state = $this->form->getState();
        $email = strtolower(trim((string) data_get($state, 'invite_email')));
        $role = (string) data_get($state, 'invite_role', OrganizationAccessService::ROLE_VIEWER);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Notification::make()->title('Please enter a valid email address.')->danger()->send();

            return;
        }

        $permissions = app(OrganizationAccessService::class)->defaultPermissionsForRole($role);
        $permissions[OrganizationAccessService::PERMISSION_MEMBERS_MANAGE] = (bool) data_get($state, 'can_manage_members', $permissions[OrganizationAccessService::PERMISSION_MEMBERS_MANAGE] ?? false);
        $permissions[OrganizationAccessService::PERMISSION_SETTINGS_MANAGE] = (bool) data_get($state, 'can_manage_settings', $permissions[OrganizationAccessService::PERMISSION_SETTINGS_MANAGE] ?? false);
        $permissions[OrganizationAccessService::PERMISSION_BILLING_READ] = (bool) data_get($state, 'can_billing_read', $permissions[OrganizationAccessService::PERMISSION_BILLING_READ] ?? false);

        $invitation = OrganizationInvitation::query()->updateOrCreate(
            [
                'organization_id' => $this->organization->id,
                'email' => $email,
                'accepted_at' => null,
                'revoked_at' => null,
            ],
            [
                'invited_by_user_id' => $user->id,
                'role' => $role,
                'permissions' => $permissions,
                'token' => hash('sha256', Str::uuid()->toString()),
                'expires_at' => now()->addDays(7),
            ],
        );

        NotificationFacade::route('mail', $email)
            ->notify(new OrganizationInvitationNotification($invitation->fresh('organization')));

        $this->form->fill([
            'invite_email' => '',
            'invite_role' => $role,
            'can_manage_members' => false,
            'can_manage_settings' => false,
            'can_billing_read' => false,
        ]);

        Notification::make()->title('Invitation sent.')->success()->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function members(): Collection
    {
        if (! $this->organization) {
            return collect();
        }

        return app(OrganizationAccessService::class)->members($this->organization);
    }

    /**
     * @return \Illuminate\Support\Collection<int, OrganizationInvitation>
     */
    public function invitations(): Collection
    {
        if (! $this->organization) {
            return collect();
        }

        return OrganizationInvitation::query()
            ->where('organization_id', $this->organization->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->latest('id')
            ->get();
    }

    public function updateMemberRole(int $userId, string $role): void
    {
        $authUser = auth()->user();
        if (! $authUser || ! $this->organization) {
            return;
        }

        abort_unless($this->canManageMembers(), 403);

        if (! in_array($role, [
            OrganizationAccessService::ROLE_VIEWER,
            OrganizationAccessService::ROLE_EDITOR,
            OrganizationAccessService::ROLE_ADMIN,
        ], true)) {
            Notification::make()->title('Invalid role selection.')->danger()->send();

            return;
        }

        $member = $this->organization->users()->where('users.id', $userId)->first(['users.id']);
        if (! $member) {
            Notification::make()->title('Member not found in this organization.')->danger()->send();

            return;
        }

        if (($member->pivot?->role ?? null) === OrganizationAccessService::ROLE_OWNER) {
            Notification::make()->title('Owner role cannot be changed here.')->warning()->send();

            return;
        }

        $this->organization->users()->updateExistingPivot($userId, [
            'role' => $role,
            'permissions' => json_encode(app(OrganizationAccessService::class)->defaultPermissionsForRole($role), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        Notification::make()->title('Member role updated.')->success()->send();
    }

    public function removeMember(int $userId): void
    {
        $authUser = auth()->user();
        if (! $authUser || ! $this->organization) {
            return;
        }

        abort_unless($this->canManageMembers(), 403);

        if ((int) $authUser->id === $userId) {
            Notification::make()->title('You cannot remove your own account.')->warning()->send();

            return;
        }

        $this->organization->users()->detach($userId);
        Notification::make()->title('Member removed from organization.')->success()->send();
    }

    public function revokeInvitation(int $invitationId): void
    {
        $authUser = auth()->user();
        if (! $authUser || ! $this->organization) {
            return;
        }

        abort_unless($this->canManageMembers(), 403);

        OrganizationInvitation::query()
            ->where('organization_id', $this->organization->id)
            ->whereKey($invitationId)
            ->whereNull('accepted_at')
            ->update([
                'revoked_at' => now(),
            ]);

        Notification::make()->title('Invitation revoked.')->success()->send();
    }

    public function canManageMembers(): bool
    {
        $user = auth()->user();

        if (! $user || ! $this->organization) {
            return false;
        }

        return app(OrganizationAccessService::class)->can(
            $user,
            $this->organization,
            OrganizationAccessService::PERMISSION_MEMBERS_MANAGE,
        );
    }
}
