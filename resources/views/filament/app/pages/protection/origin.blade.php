<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="rounded-2xl bg-gradient-to-r from-violet-500/20 via-violet-500/10 to-transparent p-6 ring-1 ring-violet-500/20 dark:from-violet-500/15 dark:via-violet-500/5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Origin</p>
                <h2 class="mt-1 text-2xl font-semibold">{{ $this->site->apex_domain }}</h2>
            </div>

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
