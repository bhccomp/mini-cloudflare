<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <style>
        .fp-rate-limit-layout {
            display: grid;
            gap: 1.5rem;
        }

        .fp-rate-limit-grid {
            display: grid;
            gap: 1.5rem;
        }

        .fp-rate-limit-grid > * {
            min-width: 0;
        }

        .fp-rate-limit-side {
            display: grid;
            gap: 1.5rem;
            align-content: start;
        }

        .fp-rate-limit-tips {
            display: grid;
            gap: 0.875rem;
        }

        .fp-rate-limit-tip {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            padding: 1rem;
            background: rgba(248, 250, 252, 0.7);
        }

        .dark .fp-rate-limit-tip {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(15, 23, 42, 0.35);
        }

        .fp-rate-limit-tip-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-rate-limit-tip-title {
            color: rgb(248 250 252);
        }

        .fp-rate-limit-tip-copy {
            margin-top: 0.35rem;
            font-size: 0.85rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-rate-limit-tip-copy {
            color: rgb(148 163 184);
        }

        .fp-rate-limit-table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
        }

        .dark .fp-rate-limit-table-wrap {
            border-color: rgba(255, 255, 255, 0.08);
        }

        .fp-rate-limit-table {
            width: 100%;
            min-width: 920px;
            border-collapse: collapse;
        }

        .fp-rate-limit-table th,
        .fp-rate-limit-table td {
            padding: 0.9rem 1rem;
            vertical-align: middle;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            text-align: left;
        }

        .fp-rate-limit-table tbody tr:first-child td {
            border-top: 0;
        }

        .dark .fp-rate-limit-table th,
        .dark .fp-rate-limit-table td {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fp-rate-limit-table th {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgb(100 116 139);
            white-space: nowrap;
        }

        .dark .fp-rate-limit-table th {
            color: rgb(148 163 184);
        }

        .fp-rate-limit-name {
            display: grid;
            gap: 0.25rem;
        }

        .fp-rate-limit-name strong {
            font-size: 0.92rem;
            color: rgb(15 23 42);
        }

        .dark .fp-rate-limit-name strong {
            color: rgb(248 250 252);
        }

        .fp-rate-limit-name span,
        .fp-rate-limit-subtle {
            font-size: 0.82rem;
            line-height: 1.45;
            color: rgb(100 116 139);
        }

        .dark .fp-rate-limit-name span,
        .dark .fp-rate-limit-subtle {
            color: rgb(148 163 184);
        }

        .fp-rate-limit-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .fp-rate-limit-state {
            min-width: 7rem;
        }

        .fp-rate-limit-presets {
            display: grid;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .fp-rate-limit-preset {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            padding: 0.9rem 1rem;
            background: rgba(248, 250, 252, 0.7);
        }

        .dark .fp-rate-limit-preset {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(15, 23, 42, 0.35);
        }

        .fp-rate-limit-preset strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-rate-limit-preset strong {
            color: rgb(248 250 252);
        }

        .fp-rate-limit-preset span {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.82rem;
            line-height: 1.45;
            color: rgb(100 116 139);
        }

        .dark .fp-rate-limit-preset span {
            color: rgb(148 163 184);
        }

        .fp-rate-limit-policy {
            display: grid;
            gap: 0.875rem;
        }

        .fp-rate-limit-policy-row {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            padding: 1rem;
            background: rgba(248, 250, 252, 0.7);
        }

        .dark .fp-rate-limit-policy-row {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(15, 23, 42, 0.35);
        }

        .fp-rate-limit-policy-row strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-rate-limit-policy-row strong {
            color: rgb(248 250 252);
        }

        .fp-rate-limit-policy-row span {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.82rem;
            line-height: 1.45;
            color: rgb(100 116 139);
        }

        .dark .fp-rate-limit-policy-row span {
            color: rgb(148 163 184);
        }

        @media (min-width: 1280px) {
            .fp-rate-limit-grid {
                grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.8fr);
                align-items: stretch;
            }
        }
    </style>

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')

            <div class="fp-rate-limit-layout">
                <div class="fp-rate-limit-grid">
                    <x-filament.app.settings.card
                        :title="$this->editingRateLimitId ? 'Edit Rate Limit Rule' : 'Create Rate Limit Rule'"
                        :description="$this->editingRateLimitId
                            ? 'Update the saved definition below. Active rules will be recreated with the new values.'
                            : 'Define request ceilings and enforcement action for paths that need extra protection.'"
                        icon="heroicon-o-clock"
                    >
                        @if ($this->editingRateLimitId)
                            <div class="mb-4 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-100">
                                Editing <strong>{{ $this->editingRateLimitName() ?: 'selected rule' }}</strong>.
                            </div>
                        @else
                            <div class="fp-rate-limit-presets">
                                @foreach ($this->rateLimitPresets() as $preset)
                                    <div class="fp-rate-limit-preset">
                                        <div>
                                            <strong>{{ $preset['name'] }}</strong>
                                            <span>{{ $preset['description'] }}</span>
                                        </div>
                                        <x-filament::button color="gray" size="sm" type="button" wire:click="applyPreset('{{ $preset['id'] }}')">
                                            Use preset
                                        </x-filament::button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form wire:submit="createRateLimit" class="space-y-4">
                            {{ $this->form }}

                            <x-filament::actions alignment="end">
                                @if ($this->editingRateLimitId)
                                    <x-filament::button color="gray" type="button" wire:click="cancelRateLimitEdit">
                                        Cancel
                                    </x-filament::button>
                                @endif
                                <x-filament::button type="submit" icon="heroicon-m-plus">
                                    {{ $this->editingRateLimitId ? 'Save rule changes' : 'Create rate limit' }}
                                </x-filament::button>
                            </x-filament::actions>
                        </form>
                    </x-filament.app.settings.card>

                    <div class="fp-rate-limit-side">
                        <x-filament.app.settings.card
                            title="Recommended Approach"
                            description="Use rate limits to absorb bursts on specific endpoints without making the whole site harder to use."
                            icon="heroicon-o-light-bulb"
                        >
                            <div class="fp-rate-limit-tips">
                                @foreach ($this->rateLimitRecommendations() as $tip)
                                    <div class="fp-rate-limit-tip">
                                        <div class="fp-rate-limit-tip-title">{{ $tip['name'] }}</div>
                                        <div class="fp-rate-limit-tip-copy">{{ $tip['description'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament.app.settings.card>

                        <x-filament.app.settings.card
                            title="Rule Behavior"
                            description="Understand what happens after a rule is enabled, disabled, or edited."
                            icon="heroicon-o-adjustments-horizontal"
                        >
                            <div class="fp-rate-limit-policy">
                                <div class="fp-rate-limit-policy-row">
                                    <strong>Enabled rules run at the edge</strong>
                                    <span>Once enabled, the rule is pushed into live traffic handling and starts applying to matching requests immediately.</span>
                                </div>

                                <div class="fp-rate-limit-policy-row">
                                    <strong>Disabled rules stay saved</strong>
                                    <span>Disabling a rule removes it from live enforcement but keeps its settings in FirePhage so you can turn it back on later.</span>
                                </div>

                                <div class="fp-rate-limit-policy-row">
                                    <strong>Edits replace the live definition</strong>
                                    <span>When you edit an active rule, FirePhage recreates it with the updated thresholds so the live edge profile stays in sync.</span>
                                </div>
                            </div>
                        </x-filament.app.settings.card>
                    </div>
                </div>

                <x-filament.app.settings.card
                    title="Configured Rules"
                    description="Active edge rules appear here first. Disabled rules are preserved in FirePhage so you can re-enable them later."
                    icon="heroicon-o-list-bullet"
                >
                    @if ($this->rateLimits === [])
                        <p class="fp-rate-limit-subtle">No custom rate limits have been created yet.</p>
                    @else
                        <div class="fp-rate-limit-table-wrap">
                            <table class="fp-rate-limit-table">
                                <thead>
                                    <tr>
                                        <th>Rule</th>
                                        <th>Action</th>
                                        <th>Window</th>
                                        <th>Requests</th>
                                        <th>Scope</th>
                                        <th>Recent traffic</th>
                                        <th>State</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->rateLimits as $rule)
                                        <tr>
                                            <td>
                                                <div class="fp-rate-limit-name">
                                                    <strong>{{ $rule['name'] }}</strong>
                                                    @if (($rule['description'] ?? '') !== '')
                                                        <span>{{ $rule['description'] }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <x-filament::badge :color="$this->rateLimitActionColor($rule)">
                                                    {{ $this->rateLimitActionLabel($rule) }}
                                                </x-filament::badge>
                                            </td>
                                            <td>{{ (int) ($rule['window_seconds'] ?? 0) }}s</td>
                                            <td>{{ number_format((int) ($rule['requests'] ?? 0)) }}</td>
                                            <td>{{ $this->rateLimitPathLabel($rule) }}</td>
                                            <td>
                                                <div class="fp-rate-limit-name">
                                                    <strong>{{ $this->rateLimitTelemetryLabel($rule) }}</strong>
                                                    <span>{{ $this->rateLimitEstimatedMatches($rule) }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fp-rate-limit-state">
                                                    <x-filament::badge :color="$this->rateLimitStateColor($rule)">
                                                        {{ (bool) ($rule['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}
                                                    </x-filament::badge>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fp-rate-limit-actions">
                                                    <x-filament::dropdown placement="bottom-end" width="xs">
                                                        <x-slot name="trigger">
                                                            <x-filament::button size="sm" color="gray" icon="heroicon-m-ellipsis-horizontal">
                                                                Actions
                                                            </x-filament::button>
                                                        </x-slot>

                                                        <x-filament::dropdown.list>
                                                            <x-filament::dropdown.list.item
                                                                tag="button"
                                                                type="button"
                                                                icon="heroicon-m-pencil-square"
                                                                wire:click="editRateLimit('{{ $rule['id'] }}')"
                                                            >
                                                                Edit
                                                            </x-filament::dropdown.list.item>

                                                            <x-filament::dropdown.list.item
                                                                tag="button"
                                                                type="button"
                                                                icon="{{ (bool) ($rule['enabled'] ?? false) ? 'heroicon-m-pause-circle' : 'heroicon-m-play-circle' }}"
                                                                wire:click="toggleRateLimit('{{ $rule['id'] }}')"
                                                            >
                                                                {{ (bool) ($rule['enabled'] ?? false) ? 'Disable' : 'Enable' }}
                                                            </x-filament::dropdown.list.item>

                                                            <x-filament::dropdown.list.item
                                                                tag="button"
                                                                type="button"
                                                                icon="heroicon-m-trash"
                                                                color="danger"
                                                                wire:click="mountAction('deleteRateLimit', { id: '{{ $rule['id'] }}' })"
                                                            >
                                                                Delete
                                                            </x-filament::dropdown.list.item>
                                                        </x-filament::dropdown.list>
                                                    </x-filament::dropdown>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
