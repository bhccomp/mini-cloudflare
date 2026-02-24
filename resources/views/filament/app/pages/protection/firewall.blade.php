<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
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

                <x-filament.app.settings.card
                    title="Recent Action"
                    description="Latest firewall operation"
                    icon="heroicon-o-clock"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Event', 'value' => $this->lastAction('waf.')],
                        ['label' => 'Rule updates', 'value' => 'Coming soon'],
                    ]" />
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
