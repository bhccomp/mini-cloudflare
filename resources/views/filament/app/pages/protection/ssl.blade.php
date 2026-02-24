<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <x-filament.app.settings.card
                        title="SSL / TLS Settings"
                        description="Manage certificate lifecycle and HTTPS transport controls."
                        icon="heroicon-o-lock-closed"
                        :status="$this->certificateStatus()"
                        :status-color="$this->site->acm_certificate_arn ? 'success' : 'warning'"
                    >
                        <x-filament.app.settings.section title="Certificate" description="Current certificate posture">
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
                    description="Recent SSL actions"
                    icon="heroicon-o-clock"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Recent event', 'value' => $this->lastAction('acm.')],
                        ['label' => 'Renewal history', 'value' => 'Coming soon'],
                    ]" />
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
