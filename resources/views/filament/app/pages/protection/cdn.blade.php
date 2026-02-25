<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @php($m = $this->site->analyticsMetric)
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="CDN Settings"
                        description="Distribution health and edge routing controls."
                        icon="heroicon-o-globe-alt"
                        :status="$this->site->cloudfront_distribution_id ? 'Connected' : 'Not deployed'"
                        :status-color="$this->site->cloudfront_distribution_id ? 'success' : 'gray'"
                    >
                        <x-filament.app.settings.section title="Edge Delivery" description="Provider-aware edge delivery posture">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Provider', 'value' => strtoupper((string) $this->site->provider)],
                                ['label' => 'Status', 'value' => $this->site->cloudfront_distribution_id ? 'Provisioned' : 'Not deployed'],
                                ['label' => 'Health', 'value' => $this->distributionHealth()],
                                ['label' => 'Edge Target', 'value' => $this->site->cloudfront_domain_name ?: 'Not assigned yet'],
                                ['label' => 'Total Requests (24h)', 'value' => number_format((int) ($m->total_requests_24h ?? 0))],
                                ['label' => 'Cache Hit Ratio', 'value' => $m && $m->cache_hit_ratio !== null ? number_format((float) $m->cache_hit_ratio, 2) . '%' : 'N/A'],
                                ['label' => 'Last action', 'value' => $this->lastAction($this->cdnActionPrefix())],
                            ]" />

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button color="gray" wire:click="refreshCdnMetrics">Refresh metrics</x-filament::button>
                                    <x-filament::button color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card title="Recent Action" description="Latest edge operation" icon="heroicon-o-clock">
                    <x-filament.app.settings.section title="Operational Events" description="Recent deployment and cache activity">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Most recent event', 'value' => $this->lastAction($this->cdnActionPrefix())],
                            ['label' => 'Captured metrics', 'value' => $m?->captured_at?->diffForHumans() ?: 'Not captured'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
