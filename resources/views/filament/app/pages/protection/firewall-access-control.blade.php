<x-filament-panels::page class="fp-waf-page">
    <x-filament.app.settings.layout-styles />

    <style>
        .fp-waf-grid {
            display: grid;
            gap: 1.5rem;
        }

        .fp-waf-stack {
            display: grid;
            gap: 1.5rem;
            align-content: start;
        }

        .fp-waf-setting-stack {
            display: grid;
            gap: 0;
        }

        .fp-waf-setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .dark .fp-waf-setting-row {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fp-waf-setting-row:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .fp-waf-setting-copy {
            display: grid;
            gap: 0.25rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .fp-waf-setting-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-waf-setting-label {
            color: rgb(248 250 252);
        }

        .fp-waf-setting-description {
            font-size: 0.875rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-waf-setting-description {
            color: rgb(148 163 184);
        }

        .fp-waf-setting-action {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex: 0 0 auto;
            text-align: right;
        }

        .fp-waf-setting-action .fi-btn-group {
            display: inline-flex;
            flex-wrap: nowrap;
        }

        .fp-waf-table {
            width: 100%;
            border-collapse: collapse;
        }

        .fp-waf-table th,
        .fp-waf-table td {
            padding: 0.85rem 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            text-align: left;
            vertical-align: top;
        }

        .fp-waf-table th:first-child,
        .fp-waf-table td:first-child {
            padding-left: 0.35rem;
        }

        .fp-waf-table th:last-child,
        .fp-waf-table td:last-child {
            padding-right: 0.35rem;
        }

        .dark .fp-waf-table th,
        .dark .fp-waf-table td {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fp-waf-table tbody tr:first-child td {
            border-top: 0;
        }

        .fp-waf-table th {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgb(100 116 139);
        }

        .dark .fp-waf-table th {
            color: rgb(148 163 184);
        }

        .fp-waf-form-grid {
            display: grid;
            gap: 1rem;
        }

        .fp-waf-field {
            display: grid;
            gap: 0.35rem;
        }

        .fp-waf-field label {
            font-size: 0.85rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-waf-field label {
            color: rgb(248 250 252);
        }

        .fp-waf-note {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            padding: 1rem;
            background: rgba(248, 250, 252, 0.7);
        }

        .dark .fp-waf-note {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(15, 23, 42, 0.35);
        }

        .fp-waf-note strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-waf-note strong {
            color: rgb(248 250 252);
        }

        .fp-waf-note span {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.85rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-waf-note span {
            color: rgb(148 163 184);
        }

        .fp-waf-page .fi-page-content .fi-wi-table > .fi-section {
            background: rgb(255 255 255);
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-ctn,
        .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-content-ctn {
            background: rgb(255 255 255);
            border-color: rgba(15, 23, 42, 0.06);
            box-shadow: none;
        }

        .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-header-cell-label,
        .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-cell,
        .fp-waf-page .fi-page-content .fi-wi-table .fi-dropdown-trigger {
            color: rgb(15 23 42);
        }

        .dark .fp-waf-page .fi-page-content .fi-wi-table > .fi-section {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(15, 23, 42, 0.82));
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 24px 50px rgba(2, 6, 23, 0.34);
        }

        .dark .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-ctn,
        .dark .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-content-ctn {
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .dark .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-header-cell-label,
        .dark .fp-waf-page .fi-page-content .fi-wi-table .fi-ta-cell,
        .dark .fp-waf-page .fi-page-content .fi-wi-table .fi-dropdown-trigger {
            color: rgb(226 232 240);
        }

        @media (min-width: 1280px) {
            .fp-waf-grid {
                grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.85fr);
                align-items: start;
            }

            .fp-waf-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .fp-waf-setting-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .fp-waf-setting-action {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>

    <div class="fp-protection-shell" wire:init="loadDeferredWafState">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')

            <div class="fp-waf-grid">
                <div class="fp-waf-stack">
                    <x-filament.app.settings.card
                        title="Access Control"
                        description="Control who can reach the site by IP, CIDR, country, continent, or ASN before the request reaches your application."
                        icon="heroicon-o-no-symbol"
                    >
                        <form wire:submit="createRules" class="space-y-4">
                            {{ $this->form }}

                            <x-filament::actions alignment="end">
                                <x-filament::button type="submit" icon="heroicon-m-shield-exclamation">
                                    Save Rule Set
                                </x-filament::button>
                                <x-filament::button
                                    tag="a"
                                    href="#configured-access-rules"
                                    color="gray"
                                    type="button"
                                >
                                    View Rules
                                </x-filament::button>
                                <x-filament::button color="gray" wire:click="deployStagedRules" wire:loading.attr="disabled" wire:target="deployStagedRules" type="button">
                                    Deploy staged rules
                                </x-filament::button>
                                <x-filament::button color="gray" wire:click="expireRulesNow" wire:loading.attr="disabled" wire:target="expireRulesNow" type="button">
                                    Expire temporary rules
                                </x-filament::button>
                            </x-filament::actions>
                        </form>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="Bot Detection"
                        description="Tune automation detection with the same direct controls FirePhage applies at the edge."
                        icon="heroicon-o-cpu-chip"
                    >
                        <div class="fp-waf-setting-stack">
                        <div class="fp-waf-setting-row">
                            <div class="fp-waf-setting-copy">
                                <div class="fp-waf-setting-label">Bot detection</div>
                                <div class="fp-waf-setting-description">Enable challenge and logging logic for suspicious automated traffic patterns before they hit the application.</div>
                            </div>
                            <div class="fp-waf-setting-action">
                                <x-filament::button.group>
                                    <x-filament::button size="xs" type="button" color="{{ ($this->botData['enabled'] ?? false) ? 'primary' : 'gray' }}" wire:click="saveBotDetectionState('enabled', true)">Enabled</x-filament::button>
                                    <x-filament::button size="xs" type="button" color="{{ ($this->botData['enabled'] ?? false) ? 'gray' : 'danger' }}" wire:click="saveBotDetectionState('enabled', false)">Disabled</x-filament::button>
                                </x-filament::button.group>
                            </div>
                        </div>

                        @foreach ([
                            'request_integrity_sensitivity' => 'Request integrity',
                            'ip_reputation_sensitivity' => 'IP reputation',
                            'browser_fingerprint_sensitivity' => 'Browser fingerprinting',
                            'browser_fingerprint_aggression' => 'Fingerprint aggression',
                        ] as $field => $label)
                            <div class="fp-waf-setting-row">
                                <div class="fp-waf-setting-copy">
                                    <div class="fp-waf-setting-label">{{ $label }}</div>
                                    <div class="fp-waf-setting-description">Set how strongly the edge should weigh this bot signal before logging or challenging a request.</div>
                                </div>
                                <div class="fp-waf-setting-action">
                                    <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                                        <x-slot name="trigger">
                                            <x-filament::button color="gray" type="button">
                                                {{ $this->botSensitivityOptions()[$this->botData[$field] ?? 1] ?? 'Balanced' }}
                                            </x-filament::button>
                                        </x-slot>
                                        <x-filament::dropdown.list>
                                            @foreach ($this->botSensitivityOptions() as $value => $labelOption)
                                                <x-filament::dropdown.list.item tag="button" type="button" wire:click="saveBotDetectionState('{{ $field }}', {{ $value }})">
                                                    {{ $labelOption }}
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </div>
                        @endforeach

                        <div class="fp-waf-setting-row">
                            <div class="fp-waf-setting-copy">
                                <div class="fp-waf-setting-label">Complex fingerprinting</div>
                                <div class="fp-waf-setting-description">Enable deeper browser analysis when traffic patterns look evasive or tool-driven.</div>
                            </div>
                            <div class="fp-waf-setting-action">
                                <x-filament::button.group>
                                    <x-filament::button size="xs" type="button" color="{{ ($this->botData['complex_fingerprinting'] ?? false) ? 'primary' : 'gray' }}" wire:click="saveBotDetectionState('complex_fingerprinting', true)">Enabled</x-filament::button>
                                    <x-filament::button size="xs" type="button" color="{{ ($this->botData['complex_fingerprinting'] ?? false) ? 'gray' : 'danger' }}" wire:click="saveBotDetectionState('complex_fingerprinting', false)">Disabled</x-filament::button>
                                </x-filament::button.group>
                            </div>
                        </div>
                        </div>
                    </x-filament.app.settings.card>

                </div>

                <div class="fp-waf-stack">
                    <x-filament.app.settings.card
                        title="Protection Snapshot"
                        description="Recent edge security outcomes across access control, WAF detections, and bot challenges."
                        icon="heroicon-o-shield-check"
                    >
                        <div class="grid gap-3">
                            @foreach ($this->wafSnapshot() as $row)
                                <div class="fp-waf-note">
                                    <strong>{{ $row['label'] }}: {{ $row['value'] }}</strong>
                                    <span>{{ $row['support'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="Bot Detection Snapshot"
                        description="Current automated-traffic posture and recent challenge volume."
                        icon="heroicon-o-cpu-chip"
                    >
                        <div class="grid gap-3">
                            <div class="fp-waf-note">
                                <strong>{{ ($this->botData['enabled'] ?? false) ? 'Bot detection enabled' : 'Bot detection disabled' }}</strong>
                                <span>{{ number_format((int) ($this->botMetrics['totalLoggedRequests'] ?? 0)) }} suspicious requests logged and {{ number_format((int) ($this->botMetrics['totalChallengedRequests'] ?? 0)) }} challenged in the recent reporting window.</span>
                            </div>
                            <div class="fp-waf-note">
                                <strong>Complex fingerprinting {{ ($this->botData['complex_fingerprinting'] ?? false) ? 'enabled' : 'disabled' }}</strong>
                                <span>Use this when you want deeper browser verification on traffic that looks synthetic or evasive.</span>
                            </div>
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="What These Controls Cover"
                        description="Use access control to shape who can reach the edge, bot detection to challenge evasive automation, and custom WAF logic for application-specific patterns."
                        icon="heroicon-o-information-circle"
                    >
                        <div class="grid gap-3">
                            <div class="fp-waf-note">
                                <strong>Access lists</strong>
                                <span>Allow, block, challenge, or log IPs, CIDRs, countries, continents, and ASNs directly at the edge.</span>
                            </div>
                            <div class="fp-waf-note">
                                <strong>Bot detection</strong>
                                <span>Adjust request integrity, IP reputation, and browser fingerprinting without changing your origin application.</span>
                            </div>
                            <div class="fp-waf-note">
                                <strong>Custom rule review</strong>
                                <span>Use advanced request rules when you need to match specific paths, headers, hosts, cookies, or other request details.</span>
                            </div>
                        </div>
                    </x-filament.app.settings.card>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
