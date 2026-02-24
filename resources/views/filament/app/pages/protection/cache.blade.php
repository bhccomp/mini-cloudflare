<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <x-filament.app.settings.card
                        title="Cache Settings"
                        description="Tune cache strategy and invalidation behavior."
                        icon="heroicon-o-circle-stack"
                        :status="ucfirst($this->cacheMode())"
                        :status-color="$this->cacheMode() === 'aggressive' ? 'warning' : 'success'"
                    >
                        <x-filament.app.settings.section title="Cache Policy" description="Current cache performance and mode">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Status', 'value' => 'Enabled'],
                                ['label' => 'Health', 'value' => $this->distributionHealth()],
                                ['label' => 'Deployment', 'value' => ucfirst($this->cacheMode()) . ' mode'],
                                ['label' => 'Cache hit ratio', 'value' => $this->metricCacheHitRatio()],
                                ['label' => 'Last action', 'value' => $this->lastAction('site.control.cache')],
                            ]" />

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button wire:click="toggleCacheMode">Switch cache mode</x-filament::button>
                                    <x-filament::button color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card
                    title="Recent Action"
                    description="Latest cache operation"
                    icon="heroicon-o-clock"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Event', 'value' => $this->lastAction('site.control.cache')],
                        ['label' => 'Edge invalidation', 'value' => 'Queued / Coming soon'],
                    ]" />
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
