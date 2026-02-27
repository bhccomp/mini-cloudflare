<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @elseif ($this->isSimpleMode())
            <x-filament::section
                heading="Simple Firewall View"
                description="Showing core threat posture and recent activity. Switch to Pro for map, top countries, top IPs, and event tables."
                icon="heroicon-o-shield-check"
            >
                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button wire:click="switchToProMode" color="gray">
                            Switch to Pro mode
                        </x-filament::button>
                    </x-filament::actions>
                </x-slot>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
