<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.site-context-header')

            <x-filament::section icon="heroicon-o-server-stack" heading="Origin" description="Origin endpoint and lock-down posture.">
                <div class="grid gap-4 md:grid-cols-4">
                    <div><p class="text-sm text-gray-500">Status</p><p class="font-medium">{{ parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured' }}</p></div>
                    <div><p class="text-sm text-gray-500">Health</p><p class="font-medium">Review direct access policy</p></div>
                    <div><p class="text-sm text-gray-500">Last deployment action</p><p class="font-medium">{{ $this->lastAction('site.control.origin') }}</p></div>
                    <div class="flex items-end"><x-filament::button size="sm" wire:click="toggleOriginProtection">Enable protection</x-filament::button></div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
