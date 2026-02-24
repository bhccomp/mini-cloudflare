<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament.app.settings.card
                title="Analytics"
                description="Traffic and threat intelligence for this site."
                icon="heroicon-o-chart-bar-square"
                status="Coming soon"
                status-color="gray"
            >
                <x-filament.app.settings.section title="Traffic Intelligence" description="Planned analytics modules">
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Traffic trend', 'value' => 'Coming soon: volume and anomaly detection'],
                        ['label' => 'Threat categories', 'value' => 'Coming soon: SQLi, XSS, bots, brute-force'],
                        ['label' => 'Origin latency', 'value' => 'Coming soon: P50/P95 upstream response times'],
                    ]" />
                </x-filament.app.settings.section>
            </x-filament.app.settings.card>
        @endif
    </div>
</x-filament-panels::page>
