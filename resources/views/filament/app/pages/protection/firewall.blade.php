<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section
                heading="Technical details"
                description="Advanced diagnostics and provider-specific metadata."
                icon="heroicon-o-wrench-screwdriver"
                collapsible
                collapsed
            >
                @php($diag = $this->diagnosticsDetails())
                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'Edge Provider', 'value' => $diag['edge_provider'] ?? 'n/a'],
                    ['label' => 'Zone ID / Pull Zone ID', 'value' => $diag['zone_id'] ?? 'n/a'],
                    ['label' => 'Site ID', 'value' => $diag['site_id'] ?? 'n/a'],
                    ['label' => 'Log Mode', 'value' => data_get($this->site->analyticsMetric?->source ?? [], 'mode', 'n/a')],
                    ['label' => 'Raw Source', 'value' => json_encode($this->site->analyticsMetric?->source ?? [], JSON_UNESCAPED_SLASHES)],
                    ['label' => 'Last Sync Timestamp', 'value' => $diag['last_sync'] ?? 'n/a'],
                    ['label' => 'API Status', 'value' => $diag['api_status'] ?? 'n/a'],
                    ['label' => 'API Response Time', 'value' => $diag['api_response_time'] ?? 'n/a'],
                    ['label' => 'Raw Health State', 'value' => $diag['raw_health'] ?? 'n/a'],
                ]" />
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
