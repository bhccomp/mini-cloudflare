<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section icon="heroicon-o-chart-bar-square" heading="Analytics" description="Traffic and threat intelligence for this site.">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-cyan-200/40 bg-cyan-500/10 p-4 dark:border-cyan-700/40">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Traffic trend</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Coming soon: request volume and anomaly detection charts.</p>
                    </div>
                    <div class="rounded-2xl border border-indigo-200/40 bg-indigo-500/10 p-4 dark:border-indigo-700/40">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Threat categories</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Coming soon: SQLi, XSS, bot and brute-force breakdown.</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/40 bg-emerald-500/10 p-4 dark:border-emerald-700/40">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Origin latency</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Coming soon: P50/P95 response times and upstream health.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
