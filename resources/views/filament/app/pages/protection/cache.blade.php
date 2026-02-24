<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="Cache Settings"
                        description="Tune cache strategy and invalidation behavior."
                        icon="heroicon-o-circle-stack"
                        :status="ucfirst($this->cacheMode())"
                        :status-color="$this->cacheMode() === 'aggressive' ? 'warning' : 'success'"
                    >
                        <x-filament.app.settings.section title="Cache Posture" description="Current mode and edge cache behavior">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Status', 'value' => 'Enabled'],
                                ['label' => 'Health', 'value' => $this->distributionHealth()],
                                ['label' => 'Deployment', 'value' => ucfirst($this->cacheMode()) . ' mode'],
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

                <x-filament.app.settings.card title="Recent Action" description="Latest cache operation" icon="heroicon-o-clock">
                    <x-filament.app.settings.section title="Operational Events" description="Recent cache updates">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Most recent event', 'value' => $this->lastAction('site.control.cache')],
                            ['label' => 'Cache hit ratio', 'value' => $this->metricCacheHitRatio()],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
