<x-filament-panels::page>
    @if (! $this->site)
        @include('filament.app.pages.protection.empty-state')
    @else
        @include('filament.app.pages.protection.site-context-header')

        <x-filament::section icon="heroicon-o-shield-check" heading="Firewall" description="Threat filtering and emergency mode controls.">
            <div class="grid gap-4 md:grid-cols-4">
                <div><p class="text-sm text-gray-500">Status</p><p class="font-medium">{{ $this->site->waf_web_acl_arn ? 'Active' : 'Pending' }}</p></div>
                <div><p class="text-sm text-gray-500">Health</p><p class="font-medium">{{ $this->site->under_attack ? 'Hardened' : 'Baseline' }}</p></div>
                <div><p class="text-sm text-gray-500">Last deployment action</p><p class="font-medium">{{ $this->lastAction('waf.') }}</p></div>
                <div class="flex items-end"><x-filament::button size="sm" color="danger" wire:click="toggleUnderAttack">{{ $this->site->under_attack ? 'Disable Under Attack' : 'Enable Under Attack' }}</x-filament::button></div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
