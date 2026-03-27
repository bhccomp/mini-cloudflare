<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <style>
        .fp-shield-grid {
            display: grid;
            gap: 1.5rem;
        }

        .fp-shield-stack {
            display: grid;
            gap: 1.5rem;
        }

        .fp-shield-notes,
        .fp-shield-stats {
            display: grid;
            gap: 0.875rem;
        }

        .fp-shield-note,
        .fp-shield-stat {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            padding: 1rem;
            background: rgba(248, 250, 252, 0.7);
        }

        .dark .fp-shield-note,
        .dark .fp-shield-stat {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(15, 23, 42, 0.35);
        }

        .fp-shield-note strong,
        .fp-shield-stat-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-shield-note strong,
        .dark .fp-shield-stat-label {
            color: rgb(248 250 252);
        }

        .fp-shield-note span,
        .fp-shield-stat-copy {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.85rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-shield-note span,
        .dark .fp-shield-stat-copy {
            color: rgb(148 163 184);
        }

        .fp-shield-stat-label {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .fp-shield-stat-value {
            margin-top: 0.35rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: rgb(15 23 42);
        }

        .dark .fp-shield-stat-value {
            color: rgb(248 250 252);
        }

        .fp-shield-traffic-table {
            width: 100%;
            border-collapse: collapse;
        }

        .fp-shield-traffic-table th,
        .fp-shield-traffic-table td {
            padding: 0.85rem 0;
            text-align: left;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .dark .fp-shield-traffic-table th,
        .dark .fp-shield-traffic-table td {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fp-shield-traffic-table tbody tr:first-child td {
            border-top: 0;
        }

        .fp-shield-traffic-table th {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgb(100 116 139);
        }

        .dark .fp-shield-traffic-table th {
            color: rgb(148 163 184);
        }

        @media (min-width: 1280px) {
            .fp-shield-grid {
                grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.8fr);
                align-items: start;
            }
        }
    </style>

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')

            <div class="fp-shield-grid">
                <x-filament.app.settings.card
                    title="Traffic Pressure Controls"
                    description="Tune the protection settings FirePhage actively applies at the edge during hostile traffic spikes."
                    icon="heroicon-o-adjustments-horizontal"
                >
                    <form wire:submit="saveSettings" class="space-y-4">
                        {{ $this->form }}

                        <x-filament::actions alignment="end">
                            <x-filament::button type="submit" icon="heroicon-m-check">
                                Save protection settings
                            </x-filament::button>
                        </x-filament::actions>
                    </form>
                </x-filament.app.settings.card>

                <div class="fp-shield-stack">
                    <x-filament.app.settings.card
                        title="Protection Snapshot"
                        description="Recent protection pressure and traffic shape for this site."
                        icon="heroicon-o-shield-check"
                    >
                        <div class="fp-shield-stats">
                            @foreach ($this->protectionSnapshot() as $row)
                                <div class="fp-shield-stat">
                                    <div class="fp-shield-stat-label">{{ $row['label'] }}</div>
                                    <div class="fp-shield-stat-value">{{ $row['value'] }}</div>
                                    <div class="fp-shield-stat-copy">{{ $row['support'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="Hot Paths"
                        description="Endpoints with the most recent edge traffic so you can see where pressure is concentrating."
                        icon="heroicon-o-bolt"
                    >
                        @if ($this->hottestPaths() === [])
                            <p class="fp-shield-stat-copy">No recent path activity has been synced yet.</p>
                        @else
                            <table class="fp-shield-traffic-table">
                                <thead>
                                    <tr>
                                        <th>Path</th>
                                        <th>Requests</th>
                                        <th>Blocked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->hottestPaths() as $row)
                                        <tr>
                                            <td>{{ $row['path'] }}</td>
                                            <td>{{ number_format($row['requests']) }}</td>
                                            <td>{{ number_format($row['blocked']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="What These Settings Control"
                        description="This page only exposes the protection controls FirePhage actively maps into the live edge profile."
                        icon="heroicon-o-information-circle"
                    >
                        <div class="fp-shield-notes">
                            <div class="fp-shield-note">
                                <strong>Edge filtering sensitivity</strong>
                                <span>Controls how aggressively suspicious requests are filtered before they reach your origin.</span>
                            </div>

                            <div class="fp-shield-note">
                                <strong>Traffic surge sensitivity</strong>
                                <span>Raises or lowers how strongly the edge reacts when incoming traffic starts to resemble hostile spikes.</span>
                            </div>

                            <div class="fp-shield-note">
                                <strong>Trusted visitor window</strong>
                                <span>Defines how long a visitor stays trusted after passing a challenge so legitimate users are not repeatedly interrupted.</span>
                            </div>

                            <div class="fp-shield-note">
                                <strong>Privacy and network filters</strong>
                                <span>Adaptive learning, privacy relay, anonymized exit, and datacenter traffic filters now map directly into the live edge profile.</span>
                            </div>
                        </div>
                    </x-filament.app.settings.card>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
