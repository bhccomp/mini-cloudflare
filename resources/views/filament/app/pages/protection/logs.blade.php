<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="rounded-2xl bg-gradient-to-r from-gray-500/20 via-gray-500/10 to-transparent p-6 ring-1 ring-gray-500/20 dark:from-gray-500/15 dark:via-gray-500/5">
                <p class="text-xs uppercase tracking-wider text-gray-500">Logs</p>
                <h2 class="mt-1 text-2xl font-semibold">{{ $this->site->apex_domain }}</h2>
            </div>

            <x-filament::section icon="heroicon-o-document-text" heading="Logs" description="Event and request logs will be available soon.">
                <p class="text-sm text-gray-600 dark:text-gray-300">Coming soon: firewall events, cache events, and deployment log streaming.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
