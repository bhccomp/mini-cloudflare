<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <style>
        .fp-org-table th,
        .fp-org-table td {
            padding-top: 0.9rem;
            padding-bottom: 0.9rem;
            vertical-align: top;
        }

        .fp-org-table td:last-child {
            min-width: 13rem;
        }

        .fp-org-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: flex-start;
        }

        .fp-org-actions .fi-btn {
            flex: 0 0 auto;
        }
    </style>

    <x-filament::section
        heading="Team Access"
        description="Invite team members and assign read/write/admin access for this organization."
        icon="heroicon-o-user-group"
    >
        @if ($this->canManageMembers())
            <form wire:submit="sendInvite" class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-end">
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                        Send Invite
                    </x-filament::button>
                </div>
            </form>
        @else
            <x-filament::badge color="gray">
                Read only access. Contact an admin to invite members.
            </x-filament::badge>
        @endif
    </x-filament::section>

    <x-filament::section
        heading="Members"
        description="Current organization members and their access levels."
        icon="heroicon-o-users"
    >
        <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="fi-ta-table fp-org-table w-full text-sm">
                <colgroup>
                    <col style="width: 42%">
                    <col style="width: 14%">
                    <col style="width: 44%">
                </colgroup>
                <thead>
                    <tr class="fi-ta-header-row">
                        <th class="fi-ta-header-cell" style="min-width: 20rem;">Member</th>
                        <th class="fi-ta-header-cell" style="min-width: 8rem;">Role</th>
                        <th class="fi-ta-header-cell" style="min-width: 20rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->members() as $member)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" style="min-width: 20rem;">
                                <div class="break-words font-medium">{{ $member->name }}</div>
                                <div class="break-all text-xs text-gray-500">{{ $member->email }}</div>
                            </td>
                            <td class="fi-ta-cell">
                                <x-filament::badge color="gray">
                                    {{ ucfirst((string) ($member->pivot->role ?? 'viewer')) }}
                                </x-filament::badge>
                            </td>
                            <td class="fi-ta-cell" style="min-width: 20rem;">
                                @if ($this->canManageMembers() && ($member->pivot->role ?? null) !== 'owner')
                                    <div class="fp-org-actions">
                                        <x-filament::button size="xs" color="gray" wire:click="updateMemberRole({{ $member->id }}, 'viewer')">Read</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="updateMemberRole({{ $member->id }}, 'editor')">Write</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="updateMemberRole({{ $member->id }}, 'admin')">Admin</x-filament::button>
                                        <x-filament::button size="xs" color="danger" wire:click="removeMember({{ $member->id }})">Remove</x-filament::button>
                                    </div>
                                @elseif (($member->pivot->role ?? null) === 'owner')
                                    <x-filament::badge color="success">Owner</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">No actions</x-filament::badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section
        heading="Pending Invites"
        description="Invitations sent but not yet accepted."
        icon="heroicon-o-envelope"
    >
        <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="fi-ta-table fp-org-table w-full text-sm">
                <colgroup>
                    <col style="width: 46%">
                    <col style="width: 14%">
                    <col style="width: 20%">
                    <col style="width: 20%">
                </colgroup>
                <thead>
                    <tr class="fi-ta-header-row">
                        <th class="fi-ta-header-cell" style="min-width: 20rem;">Email</th>
                        <th class="fi-ta-header-cell" style="min-width: 8rem;">Role</th>
                        <th class="fi-ta-header-cell" style="min-width: 10rem;">Expires</th>
                        <th class="fi-ta-header-cell" style="min-width: 9rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->invitations() as $invitation)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell break-all" style="min-width: 20rem;">{{ $invitation->email }}</td>
                            <td class="fi-ta-cell">{{ ucfirst($invitation->role) }}</td>
                            <td class="fi-ta-cell">{{ $invitation->expires_at?->diffForHumans() ?? 'n/a' }}</td>
                            <td class="fi-ta-cell">
                                @if ($this->canManageMembers())
                                    <div class="fp-org-actions">
                                        <x-filament::button size="xs" color="danger" wire:click="revokeInvitation({{ $invitation->id }})">
                                            Revoke
                                        </x-filament::button>
                                    </div>
                                @else
                                    <x-filament::badge color="gray">No actions</x-filament::badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" colspan="4">No pending invitations.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
