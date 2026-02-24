<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="Firewall Settings"
                        description="Control threat filtering and emergency hardening."
                        icon="heroicon-o-shield-check"
                        :status="$this->site->under_attack ? 'Under Attack Mode' : 'Baseline Protection'"
                        :status-color="$this->site->under_attack ? 'danger' : 'success'"
                    >
                        <x-filament.app.settings.section title="Protection Posture" description="Current firewall operating mode">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Status', 'value' => $this->site->waf_web_acl_arn ? 'Active' : 'Pending setup'],
                                ['label' => 'Health', 'value' => $this->site->under_attack ? 'Hardened' : 'Healthy'],
                                ['label' => 'Deployment', 'value' => $this->metricBlockedRequests() . ' blocked / 24h'],
                                ['label' => 'Last action', 'value' => $this->lastAction('waf.')],
                            ]" />

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button color="danger" wire:click="toggleUnderAttack">
                                        {{ $this->site->under_attack ? 'Disable Under Attack Mode' : 'Enable Under Attack Mode' }}
                                    </x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card title="Recent Action" description="Latest firewall operation" icon="heroicon-o-clock">
                    <x-filament.app.settings.section title="Operational Events" description="Recent WebACL and policy updates">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Most recent event', 'value' => $this->lastAction('waf.')],
                            ['label' => 'Rule updates', 'value' => 'Coming soon'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
