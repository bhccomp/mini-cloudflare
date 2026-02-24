<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <x-filament::section icon="heroicon-o-server-stack" heading="Origin Posture" description="Origin visibility and lock-down controls." class="xl:col-span-2">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-violet-200/40 bg-violet-500/10 p-4 dark:border-violet-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Origin host</p>
                            <p class="mt-1 text-lg font-semibold break-all">{{ parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured' }}</p>
                        </div>
                        <div class="rounded-2xl border border-yellow-200/40 bg-yellow-500/10 p-4 dark:border-yellow-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Direct access</p>
                            <p class="mt-1 text-lg font-semibold">Review policy</p>
                            <p class="mt-1 text-sm text-gray-500">Restrict origin access to edge traffic only.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button wire:click="toggleOriginProtection">Toggle origin protection</x-filament::button>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-clock" heading="Recent Action" description="Last origin operation status.">
                    <div class="rounded-xl border border-gray-200/70 bg-white/70 p-3 text-sm dark:border-gray-800 dark:bg-gray-900/60">
                        {{ $this->lastAction('site.control.origin') }}
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
