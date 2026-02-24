<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section icon="heroicon-o-document-text" heading="Logs" description="Event and request logs will be available soon.">
                <p class="text-sm text-gray-600 dark:text-gray-300">Coming soon: firewall events, cache events, and deployment log streaming.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
