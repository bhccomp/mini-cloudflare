<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="rounded-2xl bg-gradient-to-r from-red-500/20 via-red-500/10 to-transparent p-6 ring-1 ring-red-500/20 dark:from-red-500/15 dark:via-red-500/5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Firewall</p>
                <h2 class="mt-1 text-2xl font-semibold">{{ $this->site->apex_domain }}</h2>
            </div>

            <x-filament::section icon="heroicon-o-shield-check" heading="Firewall" description="Threat filtering and emergency mode controls.">
                <div class="grid gap-4 md:grid-cols-4">
                    <div><p class="text-sm text-gray-500">Status</p><p class="font-medium">{{ $this->site->waf_web_acl_arn ? 'Active' : 'Pending' }}</p></div>
                    <div><p class="text-sm text-gray-500">Health</p><p class="font-medium">{{ $this->site->under_attack ? 'Hardened' : 'Baseline' }}</p></div>
                    <div><p class="text-sm text-gray-500">Last deployment action</p><p class="font-medium">{{ $this->lastAction('waf.') }}</p></div>
                    <div class="flex items-end"><x-filament::button size="sm" color="danger" wire:click="toggleUnderAttack">{{ $this->site->under_attack ? 'Disable Under Attack' : 'Enable Under Attack' }}</x-filament::button></div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
