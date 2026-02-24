<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Blocked Requests (24h)</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $this->metricBlockedRequests() }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Cache Hit Ratio</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $this->metricCacheHitRatio() }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Certificate Status</p>
                    <div class="mt-2"><x-filament::badge :color="$this->badgeColor()">{{ $this->certificateStatus() }}</x-filament::badge></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Distribution Health</p>
                    <div class="mt-2"><x-filament::badge :color="$this->badgeColor()">{{ $this->distributionHealth() }}</x-filament::badge></div>
                </div>
            </div>

            <div class="space-y-4">
                <x-filament::section icon="heroicon-o-lock-closed" heading="SSL" description="Certificate and HTTPS controls.">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ $this->certificateStatus() }}</div></div>
                        <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->distributionHealth() }}</div></div>
                        <div><span class="text-sm text-gray-500">Last deployment action</span><div class="font-medium">{{ $this->lastAction('acm.') }}</div></div>
                        <div class="flex items-end"><x-filament::button size="sm" wire:click="toggleHttpsEnforcement">Toggle HTTPS</x-filament::button></div>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-globe-alt" heading="CDN" description="Edge routing and distribution controls.">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ $this->site->cloudfront_distribution_id ? 'Provisioned' : 'Not deployed' }}</div></div>
                        <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->distributionHealth() }}</div></div>
                        <div><span class="text-sm text-gray-500">Last deployment action</span><div class="font-medium">{{ $this->lastAction('cloudfront.') }}</div></div>
                        <div class="flex items-end"><x-filament::button size="sm" color="gray" wire:click="purgeCache">Purge cache</x-filament::button></div>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-circle-stack" heading="Cache" description="Cache behavior and invalidation controls.">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">Enabled</div></div>
                        <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->distributionHealth() }}</div></div>
                        <div><span class="text-sm text-gray-500">Last deployment action</span><div class="font-medium">{{ $this->lastAction('site.control.cache') }}</div></div>
                        <div class="flex items-end gap-2">
                            <x-filament::button size="sm" wire:click="toggleCacheMode">Mode: {{ ucfirst($this->cacheMode()) }}</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="purgeCache">Purge</x-filament::button>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-shield-check" heading="Firewall" description="Traffic filtering and attack controls.">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ $this->site->waf_web_acl_arn ? 'Active' : 'Pending' }}</div></div>
                        <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->site->under_attack ? 'Hardened' : 'Baseline' }}</div></div>
                        <div><span class="text-sm text-gray-500">Last deployment action</span><div class="font-medium">{{ $this->lastAction('waf.') }}</div></div>
                        <div class="flex items-end gap-2">
                            <x-filament::button size="sm" color="danger" wire:click="toggleUnderAttack">{{ $this->site->under_attack ? 'Disable Under Attack' : 'Enable Under Attack' }}</x-filament::button>
                            <x-filament::button size="sm" color="warning" wire:click="requestSsl">Enable protection</x-filament::button>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-server-stack" heading="Origin" description="Origin endpoint and lock-down controls.">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured' }}</div></div>
                        <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">Review direct access policy</div></div>
                        <div><span class="text-sm text-gray-500">Last deployment action</span><div class="font-medium">{{ $this->lastAction('site.control.origin') }}</div></div>
                        <div class="flex items-end"><x-filament::button size="sm" wire:click="toggleOriginProtection">Enable protection</x-filament::button></div>
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
