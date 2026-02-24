<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="Logs"
                        description="Security events and platform activity stream."
                        icon="heroicon-o-document-text"
                        status="Coming soon"
                        status-color="gray"
                    >
                        <x-filament.app.settings.section title="Event Pipeline" description="Planned logging modules">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Event stream', 'value' => 'Coming soon: filterable firewall/cache/deploy events'],
                                ['label' => 'Export', 'value' => 'Coming soon: webhook forwarding and CSV export'],
                            ]" />
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card title="Roadmap" description="Upcoming log widgets" icon="heroicon-o-clock">
                    <x-filament.app.settings.section title="Planned Releases" description="Incremental delivery order">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Phase 1', 'value' => 'Real-time event table and filters'],
                            ['label' => 'Phase 2', 'value' => 'Retention controls and quick search'],
                            ['label' => 'Phase 3', 'value' => 'Forwarding, export, and SIEM integrations'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
