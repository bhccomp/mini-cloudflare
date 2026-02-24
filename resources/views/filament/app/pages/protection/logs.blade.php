<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section icon="heroicon-o-document-text" heading="Logs" description="Security events and platform activity stream.">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/50 bg-slate-500/10 p-4 dark:border-slate-700/50">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Event stream</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Coming soon: filterable firewall, cache, and deployment events.</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-200/50 bg-zinc-500/10 p-4 dark:border-zinc-700/50">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Export</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Coming soon: webhook forwarding and downloadable CSV exports.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
