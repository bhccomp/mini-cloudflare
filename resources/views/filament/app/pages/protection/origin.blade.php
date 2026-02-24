<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="Origin Settings"
                        description="Origin visibility and access hardening controls."
                        icon="heroicon-o-server-stack"
                        status="Review access"
                        status-color="warning"
                    >
                        <x-filament.app.settings.section title="Origin Endpoint" description="Current origin exposure and lock-down state">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Status', 'value' => parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured'],
                                ['label' => 'Health', 'value' => 'Review direct access policy'],
                                ['label' => 'Deployment', 'value' => data_get($this->site->required_dns_records, 'control_panel.origin_lockdown', false) ? 'Origin lock-down enabled' : 'Origin lock-down pending'],
                                ['label' => 'Last action', 'value' => $this->lastAction('site.control.origin')],
                            ]" />

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button wire:click="toggleOriginProtection">Toggle origin protection</x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card title="Recent Action" description="Latest origin operation" icon="heroicon-o-clock">
                    <x-filament.app.settings.section title="Operational Events" description="Recent origin policy updates">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Most recent event', 'value' => $this->lastAction('site.control.origin')],
                            ['label' => 'Direct access policy', 'value' => 'Coming soon'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
