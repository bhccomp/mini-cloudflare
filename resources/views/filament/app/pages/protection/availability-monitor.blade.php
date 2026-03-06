<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')

            <x-filament::section
                heading="Monitoring Cadence"
                description="Checks run every 5 minutes on basic plans and every 1 minute on paid plans."
                icon="heroicon-o-heart"
            >
                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'Current interval', 'value' => $this->cadenceLabel()],
                    ['label' => 'Monitored domain', 'value' => (string) $this->site->apex_domain],
                    ['label' => 'Latest status', 'value' => (string) optional($this->site->availabilityChecks()->latest('checked_at')->first())->status ?: 'No checks yet'],
                ]" />
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
