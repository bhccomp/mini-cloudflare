<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <style>
        .fp-summary-grid { display:grid; gap:0.75rem; grid-template-columns:repeat(5, minmax(0, 1fr)); }
        .fp-summary-cell { border:1px solid var(--gray-200); border-radius:0.75rem; padding:0.65rem 0.75rem; background:#fff; }
        .dark .fp-summary-cell { border-color:var(--gray-800); background:rgba(15,23,42,.72); }
        .fp-summary-label { font-size:.72rem; opacity:.75; }
        .fp-summary-value { font-weight:600; font-size:1rem; }
        .fp-map-wrap { position:relative; overflow:hidden; border-radius:0.75rem; border:1px solid var(--gray-200); background:#f8fafc; }
        .dark .fp-map-wrap { border-color:var(--gray-800); background:rgba(15,23,42,.72); }
        .fp-map-point { position:absolute; transform:translate(-50%, -50%); border-radius:999px; border:1px solid rgba(15,23,42,.35); }
        .fp-breakdown { display:grid; gap:0.65rem; }
        .fp-break-row { display:grid; gap:0.3rem; }
        .fp-toggle { width:2.7rem; height:1.45rem; border-radius:999px; padding:0.15rem; border:1px solid var(--gray-300); background:#e5e7eb; display:flex; align-items:center; transition:all .15s ease; }
        .fp-toggle.on { background:#dcfce7; border-color:#22c55e; justify-content:flex-end; }
        .fp-toggle-knob { width:1.05rem; height:1.05rem; border-radius:999px; background:#fff; border:1px solid rgba(15,23,42,.15); }
        .fp-two { display:grid; gap:1rem; grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
        @media (max-width: 1100px) { .fp-summary-grid { grid-template-columns:1fr 1fr; } .fp-two { grid-template-columns:1fr; } }
    </style>

    @php($insights = $this->firewallInsights())
    @php($summary = $insights['summary'] ?? ['total' => 0, 'blocked' => 0, 'allowed' => 0, 'block_ratio' => 0])
    @php($mapPoints = $this->requestMapPoints())
    @php($threat = $this->threatLevel())
    @php($threatColor = $threat === 'Critical' ? 'danger' : ($threat === 'Warning' ? 'warning' : 'success'))
    @php($suspicious = $this->suspiciousRequests())
    @php($events = $this->filteredFirewallEvents())
    @php($maxBreak = max(1, (int) max(($summary['allowed'] ?? 0), $suspicious, ($summary['blocked'] ?? 0))))

    <div class="fp-protection-shell">
        @include('filament.app.pages.protection.technical-details')

        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section heading="Threat Summary" description="Live threat posture in the last 24 hours." icon="heroicon-o-shield-exclamation">
                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button color="gray" wire:click="refreshFirewallInsights" wire:loading.attr="disabled" wire:target="refreshFirewallInsights">Refresh insights</x-filament::button>
                    </x-filament::actions>
                </x-slot>

                <div class="fp-summary-grid">
                    <div class="fp-summary-cell">
                        <div class="fp-summary-label">Threat Level</div>
                        <div class="fp-summary-value"><x-filament::badge :color="$threatColor">{{ $threat }}</x-filament::badge></div>
                    </div>
                    <div class="fp-summary-cell">
                        <div class="fp-summary-label">Total Requests (24h)</div>
                        <div class="fp-summary-value">{{ number_format((int) ($summary['total'] ?? 0)) }}</div>
                    </div>
                    <div class="fp-summary-cell">
                        <div class="fp-summary-label">Blocked (24h)</div>
                        <div class="fp-summary-value" style="color:#dc2626;">{{ number_format((int) ($summary['blocked'] ?? 0)) }}</div>
                    </div>
                    <div class="fp-summary-cell">
                        <div class="fp-summary-label">Suspicious (24h)</div>
                        <div class="fp-summary-value" style="color:#d97706;">{{ number_format($suspicious) }}</div>
                    </div>
                    <div class="fp-summary-cell">
                        <div class="fp-summary-label">Last Sync</div>
                        <div class="fp-summary-value">{{ $this->site->analyticsMetric?->captured_at?->diffForHumans() ?: 'No sync yet' }}</div>
                    </div>
                </div>
            </x-filament::section>

            <div class="fp-two">
                <x-filament::section heading="Request Map" description="Traffic intensity and threat distribution by country." icon="heroicon-o-globe-alt">
                    <div x-data="{ zoom: 1 }" class="grid gap-3">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap;">
                            <div class="text-xs opacity-75">Zoom</div>
                            <div style="display:inline-flex; gap:.5rem; align-items:center;">
                                <x-filament::button color="gray" size="xs" x-on:click.prevent="zoom = Math.max(1, +(zoom - 0.1).toFixed(1))">-</x-filament::button>
                                <input type="range" min="1" max="2.4" step="0.1" x-model="zoom" style="width:9rem;" />
                                <x-filament::button color="gray" size="xs" x-on:click.prevent="zoom = Math.min(2.4, +(zoom + 0.1).toFixed(1))">+</x-filament::button>
                            </div>
                        </div>

                        <div class="fp-map-wrap">
                            <div x-bind:style="`transform: scale(${zoom}); transform-origin:center center; transition:transform 140ms ease;`">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/8/80/World_map_-_low_resolution.svg" alt="World map" style="width:100%; height:auto; display:block; opacity:.95;" loading="lazy" />
                                @foreach ($mapPoints as $point)
                                    @php($heat = max(10, min(95, (int) ($point['intensity'] ?? 0))))
                                    <div
                                        class="fp-map-point"
                                        style="left: {{ $point['x'] }}%; top: {{ $point['y'] }}%; width: {{ $point['size'] }}px; height: {{ $point['size'] }}px; background: rgba(30, 64, 175, {{ 0.2 + ($heat / 140) }});"
                                        title="{{ $point['country'] }} | Requests: {{ number_format((int) $point['requests']) }} | Blocked: {{ number_format((float) ($point['blocked_pct'] ?? 0), 2) }}% | Suspicious: {{ number_format((float) ($point['suspicious_pct'] ?? 0), 2) }}%"
                                    ></div>
                                @endforeach
                            </div>
                        </div>

                        @if (empty($mapPoints))
                            <p class="text-sm opacity-75">No map telemetry yet.</p>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section heading="Attack Breakdown" description="Normal, suspicious, and blocked request mix." icon="heroicon-o-chart-pie">
                    <div class="fp-breakdown">
                        <div class="fp-break-row">
                            <div class="flex items-center justify-between text-xs"><span>Normal</span><span>{{ number_format((int) ($summary['allowed'] ?? 0)) }}</span></div>
                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-green-500" style="width: {{ max(2, (int) round(((int) ($summary['allowed'] ?? 0) / $maxBreak) * 100)) }}%"></div></div>
                        </div>
                        <div class="fp-break-row">
                            <div class="flex items-center justify-between text-xs"><span>Suspicious</span><span>{{ number_format($suspicious) }}</span></div>
                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-amber-500" style="width: {{ max(2, (int) round(($suspicious / $maxBreak) * 100)) }}%"></div></div>
                        </div>
                        <div class="fp-break-row">
                            <div class="flex items-center justify-between text-xs"><span>Blocked</span><span>{{ number_format((int) ($summary['blocked'] ?? 0)) }}</span></div>
                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-red-500" style="width: {{ max(2, (int) round(((int) ($summary['blocked'] ?? 0) / $maxBreak) * 100)) }}%"></div></div>
                        </div>
                    </div>

                    <div x-data="{ enabled: @js((bool) $this->site->under_attack) }" class="mt-4 grid gap-2 rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">Under Attack Mode</p>
                                <p class="text-xs opacity-75">Applies stricter edge filtering and challenge posture.</p>
                            </div>
                            <button
                                type="button"
                                class="fp-toggle"
                                :class="enabled ? 'on' : ''"
                                x-on:click="
                                    const next = !enabled;
                                    const ok = confirm(next
                                        ? 'Enable Under Attack Mode? This will increase challenge strictness for incoming traffic.'
                                        : 'Disable Under Attack Mode and return to baseline protection?');
                                    if (!ok) return;
                                    enabled = next;
                                    $wire.toggleUnderAttack();
                                "
                            >
                                <span class="fp-toggle-knob"></span>
                            </button>
                        </div>
                        <div wire:loading wire:target="toggleUnderAttack" class="text-xs text-gray-500">Updating firewall mode...</div>
                    </div>
                </x-filament::section>
            </div>

            <x-filament::section heading="Recent Firewall Events" description="Latest traffic decisions with filtering and pagination." icon="heroicon-o-clock">
                <div class="grid gap-3 md:grid-cols-4">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="eventCountry">
                            <option value="">All Countries</option>
                            @foreach ($this->eventCountries() as $country)
                                <option value="{{ $country }}">{{ $country }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="eventAction">
                            <option value="">All Actions</option>
                            <option value="ALLOW">Allow</option>
                            <option value="BLOCK">Block</option>
                            <option value="CHALLENGE">Challenge</option>
                            <option value="CAPTCHA">Captcha</option>
                            <option value="DENY">Deny</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                @if (empty($events))
                    <p class="text-sm opacity-75">No events match the current filters.</p>
                @else
                    <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                        <table class="fi-ta-table w-full text-sm">
                            <thead>
                                <tr class="fi-ta-header-row">
                                    <th class="fi-ta-header-cell">Timestamp</th>
                                    <th class="fi-ta-header-cell">Client IP</th>
                                    <th class="fi-ta-header-cell">Country</th>
                                    <th class="fi-ta-header-cell">Method</th>
                                    <th class="fi-ta-header-cell">Path</th>
                                    <th class="fi-ta-header-cell">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($events as $event)
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">{{ \Illuminate\Support\Carbon::parse($event['timestamp'])->diffForHumans() }}</td>
                                        <td class="fi-ta-cell">{{ $event['ip'] }}</td>
                                        <td class="fi-ta-cell">{{ $event['country'] }}</td>
                                        <td class="fi-ta-cell">{{ $event['method'] }}</td>
                                        <td class="fi-ta-cell">{{ $event['uri'] }}</td>
                                        <td class="fi-ta-cell">{{ $event['action'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <x-slot name="footer">
                    <x-filament::actions alignment="between">
                        <x-filament::button color="gray" wire:click="prevEventsPage" wire:loading.attr="disabled" wire:target="prevEventsPage">Previous</x-filament::button>
                        <x-filament::button color="gray" wire:click="nextEventsPage" wire:loading.attr="disabled" wire:target="nextEventsPage">Next</x-filament::button>
                    </x-filament::actions>
                </x-slot>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
