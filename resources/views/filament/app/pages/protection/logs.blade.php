<x-filament-panels::page>
    @if (! $this->site)
        @include('filament.app.pages.protection.empty-state')
    @else
        @include('filament.app.pages.protection.site-context-header')

        <x-filament::section icon="heroicon-o-document-text" heading="Logs" description="Event and request logs will be available soon.">
            <p class="text-sm text-gray-600 dark:text-gray-300">Coming soon: firewall events, cache events, and deployment log streaming.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
