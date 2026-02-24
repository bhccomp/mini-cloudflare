<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <x-filament::section icon="heroicon-o-globe-alt" heading="Edge Delivery" description="Distribution health and traffic routing controls." class="xl:col-span-2">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-blue-200/40 bg-blue-500/10 p-4 dark:border-blue-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->site->cloudfront_distribution_id ? 'Provisioned' : 'Not deployed' }}</p>
                        </div>
                        <div class="rounded-2xl border border-cyan-200/40 bg-cyan-500/10 p-4 dark:border-cyan-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Health</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->distributionHealth() }}</p>
                        </div>
                        <div class="rounded-2xl border border-indigo-200/40 bg-indigo-500/10 p-4 dark:border-indigo-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Edge domain</p>
                            <p class="mt-1 text-sm font-semibold break-all">{{ $this->site->cloudfront_domain_name ?: 'Not assigned yet' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-command-line" heading="Recent Action" description="Last CDN operation status.">
                    <div class="rounded-xl border border-gray-200/70 bg-white/70 p-3 text-sm dark:border-gray-800 dark:bg-gray-900/60">
                        {{ $this->lastAction('cloudfront.') }}
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
