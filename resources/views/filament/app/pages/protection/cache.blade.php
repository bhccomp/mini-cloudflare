<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <x-filament::section icon="heroicon-o-circle-stack" heading="Cache Controls" description="Tune cache strategy and trigger invalidation." class="xl:col-span-2">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-emerald-200/40 bg-emerald-500/10 p-4 dark:border-emerald-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Mode</p>
                            <p class="mt-1 text-lg font-semibold">{{ ucfirst($this->cacheMode()) }}</p>
                        </div>
                        <div class="rounded-2xl border border-teal-200/40 bg-teal-500/10 p-4 dark:border-teal-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Cache hit ratio</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->metricCacheHitRatio() }}</p>
                        </div>
                        <div class="rounded-2xl border border-cyan-200/40 bg-cyan-500/10 p-4 dark:border-cyan-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Health</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->distributionHealth() }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button wire:click="toggleCacheMode">Switch cache mode</x-filament::button>
                        <x-filament::button color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-clock" heading="Recent Action" description="Last cache operation status.">
                    <div class="rounded-xl border border-gray-200/70 bg-white/70 p-3 text-sm dark:border-gray-800 dark:bg-gray-900/60">
                        {{ $this->lastAction('site.control.cache') }}
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
