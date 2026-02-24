<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-8 pb-2">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section icon="heroicon-o-shield-check" heading="Protection Control Stack" description="Operate SSL, CDN, cache, firewall, and origin controls for the selected site.">
                <div class="grid gap-4 lg:grid-cols-2">
                    <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">SSL / TLS</p>
                                <p class="mt-1 text-lg font-semibold">{{ $this->certificateStatus() }}</p>
                                <p class="text-sm text-gray-500">{{ $this->lastAction('acm.') }}</p>
                            </div>
                            <x-filament::badge :color="$this->badgeColor()">{{ str($this->site->status)->replace('_', ' ')->title() }}</x-filament::badge>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button size="sm" wire:click="requestSsl">Request SSL</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="toggleHttpsEnforcement">HTTPS mode</x-filament::button>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">CDN / Cache</p>
                                <p class="mt-1 text-lg font-semibold">{{ $this->distributionHealth() }}</p>
                                <p class="text-sm text-gray-500">{{ $this->lastAction('cloudfront.') }}</p>
                            </div>
                            <x-filament::badge :color="$this->site->cloudfront_distribution_id ? 'success' : 'gray'">
                                {{ $this->site->cloudfront_distribution_id ? 'Connected' : 'Not deployed' }}
                            </x-filament::badge>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button size="sm" color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                            <x-filament::button size="sm" wire:click="toggleCacheMode">Cache: {{ ucfirst($this->cacheMode()) }}</x-filament::button>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Firewall</p>
                                <p class="mt-1 text-lg font-semibold">{{ $this->site->under_attack ? 'Under Attack Mode' : 'Baseline Protection' }}</p>
                                <p class="text-sm text-gray-500">{{ $this->lastAction('waf.') }}</p>
                            </div>
                            <x-filament::badge :color="$this->site->under_attack ? 'danger' : 'success'">
                                {{ $this->site->under_attack ? 'Hardened' : 'Healthy' }}
                            </x-filament::badge>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button size="sm" color="danger" wire:click="toggleUnderAttack">
                                {{ $this->site->under_attack ? 'Disable Under Attack' : 'Enable Under Attack' }}
                            </x-filament::button>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Origin</p>
                                <p class="mt-1 text-lg font-semibold">{{ parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured' }}</p>
                                <p class="text-sm text-gray-500">{{ $this->lastAction('site.control.origin') }}</p>
                            </div>
                            <x-filament::badge color="warning">Review access</x-filament::badge>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button size="sm" wire:click="toggleOriginProtection">Origin protection</x-filament::button>
                        </div>
                    </article>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
