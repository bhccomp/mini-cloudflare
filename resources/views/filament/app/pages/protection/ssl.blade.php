<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="SSL / TLS Settings"
                        description="Manage certificate lifecycle and HTTPS transport controls."
                        icon="heroicon-o-lock-closed"
                        :status="$this->certificateStatus()"
                        :status-color="$this->site->acm_certificate_arn ? 'success' : 'warning'"
                    >
                        <x-filament.app.settings.section title="Certificate Posture" description="Current TLS issuance and delivery state">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Status', 'value' => $this->certificateStatus()],
                                ['label' => 'Health', 'value' => $this->distributionHealth()],
                                ['label' => 'Deployment', 'value' => $this->site->acm_certificate_arn ? 'Certificate requested' : 'Not started'],
                                ['label' => 'Last action', 'value' => $this->lastAction('acm.')],
                            ]" />

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button wire:click="requestSsl">Request certificate</x-filament::button>
                                    <x-filament::button color="gray" wire:click="toggleHttpsEnforcement">Toggle HTTPS enforcement</x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card
                    title="Deployment Timeline"
                    description="Recent SSL lifecycle updates"
                    icon="heroicon-o-clock"
                >
                    <x-filament.app.settings.section title="Recent Events" description="Latest certificate and validation operations">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Most recent event', 'value' => $this->lastAction('acm.')],
                            ['label' => 'Validation state', 'value' => $this->certificateStatus()],
                            ['label' => 'Renewal history', 'value' => 'Coming soon'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
