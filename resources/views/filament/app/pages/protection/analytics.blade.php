<x-filament-panels::page>
    @if (! $this->site)
        @include('filament.app.pages.protection.empty-state')
    @else
        @include('filament.app.pages.protection.site-context-header')

        <x-filament::section icon="heroicon-o-chart-bar-square" heading="Analytics" description="Traffic analytics will be available soon.">
            <p class="text-sm text-gray-600 dark:text-gray-300">Coming soon: request trends, threat categories, and origin response analytics.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
