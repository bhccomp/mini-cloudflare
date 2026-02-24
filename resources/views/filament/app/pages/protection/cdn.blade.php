<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="CDN Settings"
                        description="Distribution health and edge routing controls."
                        icon="heroicon-o-globe-alt"
                        :status="$this->site->cloudfront_distribution_id ? 'Connected' : 'Not deployed'"
                        :status-color="$this->site->cloudfront_distribution_id ? 'success' : 'gray'"
                    >
                        <x-filament.app.settings.section title="Edge Delivery" description="CloudFront provisioning and traffic posture">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Status', 'value' => $this->site->cloudfront_distribution_id ? 'Provisioned' : 'Not deployed'],
                                ['label' => 'Health', 'value' => $this->distributionHealth()],
                                ['label' => 'Deployment', 'value' => $this->site->cloudfront_domain_name ?: 'Not assigned yet'],
                                ['label' => 'Last action', 'value' => $this->lastAction('cloudfront.')],
                            ]" />

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card title="Recent Action" description="Latest CDN operation" icon="heroicon-o-clock">
                    <x-filament.app.settings.section title="Operational Events" description="Latest edge deployment updates">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Most recent event', 'value' => $this->lastAction('cloudfront.')],
                            ['label' => 'Propagation', 'value' => 'Coming soon'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
