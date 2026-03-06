<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')

            <x-filament::section
                heading="DDoS & Challenge Profile"
                description="Tune sensitivity and challenge behavior for edge protection."
                icon="heroicon-o-adjustments-horizontal"
            >
                <form wire:submit="saveSettings" class="space-y-4">
                    {{ $this->form }}

                    <x-filament::actions alignment="end">
                        <x-filament::button type="submit" icon="heroicon-m-check">
                            Save security settings
                        </x-filament::button>
                    </x-filament::actions>
                </form>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
