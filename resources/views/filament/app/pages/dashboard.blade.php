<x-filament-panels::page>
    @if (! $this->site)
        <x-filament::section icon="heroicon-o-globe-alt" heading="Select a site" description="Choose a site from the topbar switcher to load its protection controls.">
            <div class="flex flex-wrap gap-3">
                <x-filament::button tag="a" :href="\App\Filament\App\Resources\SiteResource::getUrl('index')" color="gray">
                    Go to Sites
                </x-filament::button>
                <x-filament::button tag="a" :href="\App\Filament\App\Resources\SiteResource::getUrl('create')">
                    Add site
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section heading="Blocked Requests (24h)">
                <div class="text-2xl font-semibold">{{ $this->metricBlockedRequests() }}</div>
            </x-filament::section>
            <x-filament::section heading="Cache Hit Ratio">
                <div class="text-2xl font-semibold">{{ $this->metricCacheHitRatio() }}</div>
            </x-filament::section>
            <x-filament::section heading="Certificate Status">
                <x-filament::badge :color="$this->badgeColor($this->site->status)">{{ $this->certificateStatus() }}</x-filament::badge>
            </x-filament::section>
            <x-filament::section heading="Distribution Health">
                <x-filament::badge :color="$this->badgeColor($this->site->status)">{{ $this->distributionHealth() }}</x-filament::badge>
            </x-filament::section>
        </div>

        <div class="space-y-4">
            <x-filament::section icon="heroicon-o-lock-closed" heading="SSL" description="Certificate and secure connection controls.">
                <div class="grid gap-3 md:grid-cols-4">
                    <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ $this->certificateStatus() }}</div></div>
                    <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->distributionHealth() }}</div></div>
                    <div><span class="text-sm text-gray-500">Last action</span><div class="font-medium">{{ $this->lastAction('acm.') }}</div></div>
                    <div class="flex items-end"><x-filament::button size="sm" wire:click="toggleHttpsEnforcement">Toggle HTTPS</x-filament::button></div>
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-cloud" heading="CDN" description="Traffic routing and edge delivery status.">
                <div class="grid gap-3 md:grid-cols-4">
                    <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ $this->site->cloudfront_distribution_id ? 'Provisioned' : 'Not deployed' }}</div></div>
                    <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->distributionHealth() }}</div></div>
                    <div><span class="text-sm text-gray-500">Last action</span><div class="font-medium">{{ $this->lastAction('cloudfront.') }}</div></div>
                    <div class="flex items-end"><x-filament::button size="sm" color="gray" wire:click="purgeCache">Purge cache</x-filament::button></div>
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-bolt" heading="Cache" description="Edge cache behavior and invalidation controls.">
                <div class="grid gap-3 md:grid-cols-4">
                    <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">Enabled</div></div>
                    <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->distributionHealth() }}</div></div>
                    <div><span class="text-sm text-gray-500">Last action</span><div class="font-medium">{{ $this->lastAction('site.control.cache') }}</div></div>
                    <div class="flex items-end gap-2">
                        <x-filament::button size="sm" wire:click="toggleCacheMode">Mode: {{ ucfirst($this->cacheMode()) }}</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="purgeCache">Purge</x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-shield-check" heading="WAF" description="Threat filtering and emergency protection controls.">
                <div class="grid gap-3 md:grid-cols-4">
                    <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ $this->site->waf_web_acl_arn ? 'Active' : 'Pending' }}</div></div>
                    <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">{{ $this->site->under_attack ? 'Hardened' : 'Baseline' }}</div></div>
                    <div><span class="text-sm text-gray-500">Last action</span><div class="font-medium">{{ $this->lastAction('waf.') }}</div></div>
                    <div class="flex items-end gap-2">
                        <x-filament::button size="sm" color="danger" wire:click="toggleUnderAttack">{{ $this->site->under_attack ? 'Disable Under Attack' : 'Enable Under Attack' }}</x-filament::button>
                        <x-filament::button size="sm" color="warning" wire:click="requestSsl">Enable protection</x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-server-stack" heading="Origin" description="Origin endpoint exposure and protection posture.">
                <div class="grid gap-3 md:grid-cols-4">
                    <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured' }}</div></div>
                    <div><span class="text-sm text-gray-500">Health</span><div class="font-medium">Review direct access policy</div></div>
                    <div><span class="text-sm text-gray-500">Last action</span><div class="font-medium">{{ $this->lastAction('site.control.origin') }}</div></div>
                    <div class="flex items-end"><x-filament::button size="sm" wire:click="toggleOriginProtection">Enable protection</x-filament::button></div>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
