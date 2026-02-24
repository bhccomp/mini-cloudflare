<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="fp-protection-grid">
                <div>
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
                </div>

                <x-filament.app.settings.card title="Roadmap" description="Upcoming analytics widgets" icon="heroicon-o-clock">
                    <x-filament.app.settings.section title="Planned Releases" description="Incremental delivery order">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Phase 1', 'value' => 'Traffic and blocked-request trend charts'],
                            ['label' => 'Phase 2', 'value' => 'Threat category breakdown'],
                            ['label' => 'Phase 3', 'value' => 'Regional and latency insights'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
