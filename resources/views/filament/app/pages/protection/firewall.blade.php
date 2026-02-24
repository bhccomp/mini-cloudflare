<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <x-filament::section icon="heroicon-o-shield-check" heading="Firewall Controls" description="Threat filtering and emergency hardening." class="xl:col-span-2">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-rose-200/40 bg-rose-500/10 p-4 dark:border-rose-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Protection mode</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->site->under_attack ? 'Under Attack' : 'Baseline' }}</p>
                        </div>
                        <div class="rounded-2xl border border-orange-200/40 bg-orange-500/10 p-4 dark:border-orange-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">WAF status</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->site->waf_web_acl_arn ? 'Active' : 'Pending setup' }}</p>
                        </div>
                        <div class="rounded-2xl border border-amber-200/40 bg-amber-500/10 p-4 dark:border-amber-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Blocked requests (24h)</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->metricBlockedRequests() }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button color="danger" wire:click="toggleUnderAttack">
                            {{ $this->site->under_attack ? 'Disable Under Attack Mode' : 'Enable Under Attack Mode' }}
                        </x-filament::button>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-clock" heading="Recent Action" description="Last firewall operation status.">
                    <div class="rounded-xl border border-gray-200/70 bg-white/70 p-3 text-sm dark:border-gray-800 dark:bg-gray-900/60">
                        {{ $this->lastAction('waf.') }}
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
