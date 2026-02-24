<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <style>
        .fp-firewall-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fp-firewall-list {
            display: grid;
            gap: 0.5rem;
        }

        .fp-firewall-table-wrap {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .dark .fp-firewall-table-wrap {
            border-color: var(--gray-800);
        }

        .fp-firewall-table-wrap .fi-ta-table th,
        .fp-firewall-table-wrap .fi-ta-table td {
            font-size: 0.8rem;
            line-height: 1.25;
            padding-block: 0.45rem;
        }

        .fp-firewall-table-wrap .fi-ta-table tbody tr:nth-child(odd) td {
            background: #ffffff;
        }

        .fp-firewall-table-wrap .fi-ta-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .dark .fp-firewall-table-wrap .fi-ta-table tbody tr:nth-child(odd) td {
            background: rgba(15, 23, 42, 0.55);
        }

        .dark .fp-firewall-table-wrap .fi-ta-table tbody tr:nth-child(even) td {
            background: rgba(15, 23, 42, 0.72);
        }

        .fp-firewall-map {
            width: 100%;
            height: auto;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            background: linear-gradient(180deg, rgba(56, 189, 248, 0.08), rgba(56, 189, 248, 0.02));
        }

        .dark .fp-firewall-map {
            border-color: var(--gray-800);
        }

        .fp-map-canvas {
            position: relative;
            width: 100%;
            border-radius: 0.75rem;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            background: #f8fafc;
        }

        .dark .fp-map-canvas {
            border-color: var(--gray-800);
            background: rgba(15, 23, 42, 0.78);
        }

        .fp-map-image {
            width: 100%;
            height: auto;
            display: block;
            filter: grayscale(0.25) contrast(1.05);
            opacity: 0.95;
        }

        .dark .fp-map-image {
            filter: grayscale(0.1) contrast(1.08) brightness(0.92);
            opacity: 0.9;
        }

        .fp-map-point {
            position: absolute;
            transform: translate(-50%, -50%);
            border-radius: 999px;
            background: rgba(34, 197, 94, 0.35);
            border: 1px solid rgba(34, 197, 94, 0.9);
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.15);
        }

        .fp-map-stage {
            transform-origin: center center;
            transition: transform 160ms ease;
        }

        @media (max-width: 1024px) {
            .fp-firewall-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php($insights = $this->firewallInsights())
    @php($summary = $insights['summary'] ?? ['total' => 0, 'blocked' => 0, 'allowed' => 0, 'counted' => 0, 'block_ratio' => 0])
    @php($mapPoints = $this->requestMapPoints())

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section heading="Firewall Overview" description="Live traffic enforcement insights, sampled request events, and geographic request distribution." icon="heroicon-o-shield-check">
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->site->under_attack ? 'danger' : 'success'">
                        {{ $this->site->under_attack ? 'Under Attack Mode' : 'Baseline Protection' }}
                    </x-filament::badge>
                </x-slot>

                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button color="gray" wire:click="refreshFirewallInsights" wire:loading.attr="disabled" wire:target="refreshFirewallInsights">
                            Refresh insights
                        </x-filament::button>
                        <x-filament::button color="danger" wire:click="toggleUnderAttack" wire:loading.attr="disabled" wire:target="toggleUnderAttack">
                            {{ $this->site->under_attack ? 'Disable Under Attack Mode' : 'Enable Under Attack Mode' }}
                        </x-filament::button>
                    </x-filament::actions>
                </x-slot>

                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'WAF Status', 'value' => $this->site->waf_web_acl_arn ? 'Active' : 'Pending setup'],
                    ['label' => 'Total Requests (sample)', 'value' => number_format((int) ($summary['total'] ?? 0))],
                    ['label' => 'Blocked Requests', 'value' => number_format((int) ($summary['blocked'] ?? 0))],
                    ['label' => 'Allowed Requests', 'value' => number_format((int) ($summary['allowed'] ?? 0))],
                    ['label' => 'Counted Requests', 'value' => number_format((int) ($summary['counted'] ?? 0))],
                    ['label' => 'Block Ratio', 'value' => number_format((float) ($summary['block_ratio'] ?? 0), 2) . '%'],
                    ['label' => 'Last Firewall Action', 'value' => $this->lastAction('waf.')],
                    ['label' => 'Data Source', 'value' => strtoupper((string) ($insights['source'] ?? 'none'))],
                ]" />
            </x-filament::section>

            <x-filament::section heading="Request Map" description="Top country request origins for this site." icon="heroicon-o-globe-alt">
                <div x-data="{ zoom: 1 }" style="display: grid; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                        <div class="text-xs opacity-75">Zoom</div>
                        <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <x-filament::button color="gray" size="xs" x-on:click.prevent="zoom = Math.max(1, +(zoom - 0.1).toFixed(1))">-</x-filament::button>
                            <input type="range" min="1" max="2.5" step="0.1" x-model="zoom" style="width: 10rem;" />
                            <x-filament::button color="gray" size="xs" x-on:click.prevent="zoom = Math.min(2.5, +(zoom + 0.1).toFixed(1))">+</x-filament::button>
                            <x-filament::badge color="gray" x-text="zoom.toFixed(1) + 'x'"></x-filament::badge>
                        </div>
                    </div>

                    <div class="fp-map-canvas">
                        <div class="fp-map-stage" x-bind:style="`transform: scale(${zoom})`">
                            <img
                                class="fp-map-image"
                                src="https://upload.wikimedia.org/wikipedia/commons/8/80/World_map_-_low_resolution.svg"
                                alt="World map"
                                loading="lazy"
                            />

                            @foreach ($mapPoints as $point)
                                <div
                                    class="fp-map-point"
                                    style="left: {{ $point['x'] }}%; top: {{ $point['y'] }}%; width: {{ $point['size'] }}px; height: {{ $point['size'] }}px;"
                                    title="{{ $point['country'] }}: {{ number_format((int) $point['requests']) }} requests"
                                ></div>
                            @endforeach
                        </div>
                    </div>

                    @if (empty($mapPoints))
                        <p class="text-sm opacity-75">No request map data yet. Run refresh once traffic is flowing through WAF.</p>
                    @endif
                </div>
            </x-filament::section>

            <div class="fp-firewall-grid">
                <x-filament::section heading="Top Countries" description="Highest request volume by country." icon="heroicon-o-map">
                    @if (empty($insights['top_countries'] ?? []))
                        <p class="text-sm opacity-75">{{ $insights['message'] ?? 'No country data available yet.' }}</p>
                    @else
                        <div class="fp-firewall-table-wrap fi-ta-content-ctn">
                            <div class="fi-ta-content">
                                <table class="fi-ta-table">
                                    <thead>
                                        <tr class="fi-ta-row fi-ta-header-row">
                                            <th class="fi-ta-header-cell">Country</th>
                                            <th class="fi-ta-header-cell fi-align-end">Requests</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (($insights['top_countries'] ?? []) as $row)
                                            <tr class="fi-ta-row">
                                                <td class="fi-ta-cell">{{ $row['country'] }}</td>
                                                <td class="fi-ta-cell fi-align-end">{{ number_format((int) $row['requests']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Top IPs" description="Most active client IPs from sampled firewall requests." icon="heroicon-o-server-stack">
                    @if (empty($insights['top_ips'] ?? []))
                        <p class="text-sm opacity-75">{{ $insights['message'] ?? 'No IP data available yet.' }}</p>
                    @else
                        <div class="fp-firewall-table-wrap fi-ta-content-ctn">
                            <div class="fi-ta-content">
                                <table class="fi-ta-table">
                                    <thead>
                                        <tr class="fi-ta-row fi-ta-header-row">
                                            <th class="fi-ta-header-cell">IP</th>
                                            <th class="fi-ta-header-cell fi-align-end">Requests</th>
                                            <th class="fi-ta-header-cell fi-align-end">Blocked</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (($insights['top_ips'] ?? []) as $row)
                                            <tr class="fi-ta-row">
                                                <td class="fi-ta-cell">{{ $row['ip'] }}</td>
                                                <td class="fi-ta-cell fi-align-end">{{ number_format((int) $row['requests']) }}</td>
                                                <td class="fi-ta-cell fi-align-end">{{ number_format((int) ($row['blocked'] ?? 0)) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            </div>

            <x-filament::section heading="Recent Firewall Events" description="Sampled request decisions from AWS WAF." icon="heroicon-o-clock">
                @if (empty($insights['events'] ?? []))
                    <p class="text-sm opacity-75">{{ $insights['message'] ?? 'No event data available yet.' }}</p>
                @else
                    <div class="fp-firewall-table-wrap fi-ta-content-ctn">
                        <div class="fi-ta-content">
                            <table class="fi-ta-table">
                                <thead>
                                    <tr class="fi-ta-row fi-ta-header-row">
                                        <th class="fi-ta-header-cell">Time</th>
                                        <th class="fi-ta-header-cell">Action</th>
                                        <th class="fi-ta-header-cell">Country</th>
                                        <th class="fi-ta-header-cell">IP</th>
                                        <th class="fi-ta-header-cell">URI</th>
                                        <th class="fi-ta-header-cell">Rule</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (($insights['events'] ?? []) as $event)
                                        <tr class="fi-ta-row">
                                            <td class="fi-ta-cell">{{ \Illuminate\Support\Carbon::parse($event['timestamp'])->diffForHumans() }}</td>
                                            <td class="fi-ta-cell">{{ $event['action'] }}</td>
                                            <td class="fi-ta-cell">{{ $event['country'] }}</td>
                                            <td class="fi-ta-cell">{{ $event['ip'] }}</td>
                                            <td class="fi-ta-cell">{{ $event['method'] }} {{ $event['uri'] }}</td>
                                            <td class="fi-ta-cell">{{ $event['rule'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
