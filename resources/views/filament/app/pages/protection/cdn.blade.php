<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($m = $this->site?->analyticsMetric)
    @php($requestsTrend = $this->requestsTrend())
    @php($bandwidthTrend = $this->bandwidthTrend())
    @php($topPaths = $this->topCachedPaths())
    @php($requestsMax = max(1, (int) collect($requestsTrend)->max('requests')))
    @php($bandwidthUsage = $this->bandwidthUsageSummary())

    <div class="fp-protection-shell">
        @include('filament.app.pages.protection.technical-details')

        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section heading="Edge Delivery" description="Live edge traffic and caching posture." icon="heroicon-o-globe-alt">
                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button
                            color="{{ $this->isDevelopmentMode() ? 'warning' : 'gray' }}"
                            wire:click="toggleDevelopmentMode"
                            wire:loading.attr="disabled"
                            wire:target="toggleDevelopmentMode"
                        >
                            {{ $this->isDevelopmentMode() ? 'Disable Development Mode' : 'Enable Development Mode' }}
                        </x-filament::button>
                        <x-filament::button color="gray" wire:click="refreshCdnMetrics" wire:loading.attr="disabled" wire:target="refreshCdnMetrics">Refresh metrics</x-filament::button>
                        <x-filament::button color="gray" wire:click="purgeCache" wire:loading.attr="disabled" wire:target="purgeCache" :disabled="$this->isDevelopmentMode()">Purge cache</x-filament::button>
                    </x-filament::actions>
                </x-slot>

                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'Development Mode', 'value' => $this->isDevelopmentMode() ? 'Enabled (cache + acceleration disabled)' : 'Disabled'],
                    ['label' => 'Edge Network', 'value' => $this->isDevelopmentMode() ? 'Connected (development bypass mode)' : ($this->site->cloudfront_distribution_id ? 'Connected' : 'Pending setup')],
                    ['label' => 'Edge Status', 'value' => $this->isDevelopmentMode() ? 'Development mode' : ($this->distributionHealth() === 'Healthy' ? 'Healthy' : 'Provisioning')],
                    ['label' => 'Requests (24h)', 'value' => number_format((int) ($m->total_requests_24h ?? 0))],
                    ['label' => 'Bandwidth (24h)', 'value' => number_format((((int) ($m->cached_requests_24h ?? 0) + (int) ($m->origin_requests_24h ?? 0)) * 0.34), 2) . ' MB'],
                    ['label' => 'Monthly Usage', 'value' => number_format((float) $bandwidthUsage['usage_gb'], 2) . ' GB / ' . number_format((int) $bandwidthUsage['included_gb']) . ' GB'],
                    ['label' => 'Included Usage', 'value' => number_format((float) $bandwidthUsage['percent_used'], 2) . '%' . ((bool) $bandwidthUsage['warning'] ? ' (Warning: close to limit)' : '')],
                    ['label' => 'Cache Hit %', 'value' => $m && $m->cache_hit_ratio !== null ? number_format((float) $m->cache_hit_ratio, 2) . '%' : 'No telemetry yet'],
                    ['label' => 'Origin Offload %', 'value' => number_format($this->originOffloadRatio(), 2) . '%'],
                    ['label' => 'Last Sync', 'value' => $m?->captured_at?->diffForHumans() ?: 'Not synced yet'],
                ]" />
            </x-filament::section>

            @if ($this->isSimpleMode())
                <x-filament::section heading="Want Deeper CDN Insights?" icon="heroicon-o-adjustments-horizontal">
                    <p class="text-sm">Simple mode shows the essentials. Switch to Pro for trend analytics and cached path breakdowns.</p>
                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button wire:click="switchToProMode" color="gray">Switch to Pro mode</x-filament::button>
                        </x-filament::actions>
                    </x-slot>
                </x-filament::section>
            @else
            <x-filament::section heading="7-Day Trend" description="Requests and estimated bandwidth over the last 7 days." icon="heroicon-o-arrow-trending-up">
                @if (empty($requestsTrend))
                    <p class="text-sm opacity-75">No trend data yet. Refresh after live traffic reaches the edge network.</p>
                @else
                    <div class="grid gap-2">
                        @foreach ($requestsTrend as $idx => $point)
                            @php($width = max(3, (int) round(($point['requests'] / $requestsMax) * 100)))
                            <div class="grid gap-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span>{{ $point['label'] }}</span>
                                    <span>{{ number_format((int) $point['requests']) }} req · {{ number_format((float) ($bandwidthTrend[$idx]['bandwidth_mb'] ?? 0), 2) }} MB</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-2 rounded-full bg-primary-500" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Top Cached Paths" description="Most requested edge-cached paths in the current sample window." icon="heroicon-o-document-duplicate">
                @if (empty($topPaths))
                    <p class="text-sm opacity-75">No cached path telemetry yet.</p>
                @else
                    <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                        <table class="fi-ta-table w-full text-sm">
                            <thead>
                                <tr class="fi-ta-header-row">
                                    <th class="fi-ta-header-cell">Path</th>
                                    <th class="fi-ta-header-cell fi-align-end">Requests</th>
                                    <th class="fi-ta-header-cell fi-align-end">Bandwidth</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topPaths as $row)
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">{{ $row['path'] }}</td>
                                        <td class="fi-ta-cell fi-align-end">{{ number_format((int) $row['hits']) }}</td>
                                        <td class="fi-ta-cell fi-align-end">{{ number_format((float) $row['bandwidth_mb'], 2) }} MB</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
