<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
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
        @endif
    </div>
</x-filament-panels::page>
