<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <x-filament.app.settings.card
                        title="CDN Settings"
                        description="Distribution health and edge routing controls."
                        icon="heroicon-o-globe-alt"
                        :status="$this->site->cloudfront_distribution_id ? 'Connected' : 'Not deployed'"
                        :status-color="$this->site->cloudfront_distribution_id ? 'success' : 'gray'"
                    >
                        <x-filament.app.settings.section title="Distribution" description="Current edge delivery state">
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

                <x-filament.app.settings.card
                    title="Recent Action"
                    description="Latest CDN operation"
                    icon="heroicon-o-command-line"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Event', 'value' => $this->lastAction('cloudfront.')],
                        ['label' => 'Propagation', 'value' => 'In progress / Coming soon'],
                    ]" />
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
