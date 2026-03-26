<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($trend = $this->cacheHitTrend())
    @php($misses = $this->topCacheMisses())
    @php($history = $this->purgeHistory())
    @php($cacheEnabled = $this->isCacheEnabled())
    @php($cacheMode = ucfirst($this->cacheMode()))
    @php($developmentMode = $this->isDevelopmentMode())
    @php($troubleshootingMode = $this->isTroubleshootingMode())

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')
            @include('filament.app.pages.protection.technical-details')

            <div class="fp-protection-grid">
                <x-filament.app.settings.card
                    title="Cache Control"
                    description="Live cache settings that FirePhage now applies directly to the Bunny edge."
                    icon="heroicon-o-circle-stack"
                    :status="$developmentMode ? 'Development Mode' : ($cacheEnabled ? $cacheMode : 'Caching Off')"
                    :status-color="$developmentMode ? 'warning' : ($cacheEnabled ? 'success' : 'gray')"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Caching', 'value' => $cacheEnabled ? 'Enabled' : 'Disabled'],
                        ['label' => 'Mode', 'value' => $cacheMode],
                        ['label' => 'Cache Hit Ratio', 'value' => $this->metricCacheHitRatio()],
                        ['label' => 'Last Cache Change', 'value' => $this->lastAction(['site.control.cache_enabled', 'site.control.cache_mode'])],
                        ['label' => 'Last Sync', 'value' => $this->site->syncFreshnessForHumans('Not synced yet')],
                    ]" />

                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button
                                color="{{ $cacheEnabled ? 'gray' : 'primary' }}"
                                wire:click="toggleCacheEnabled"
                                wire:loading.attr="disabled"
                                wire:target="toggleCacheEnabled"
                            >
                                {{ $cacheEnabled ? 'Disable Cache' : 'Enable Cache' }}
                            </x-filament::button>
                            <x-filament::button
                                color="gray"
                                wire:click="toggleCacheMode"
                                wire:loading.attr="disabled"
                                wire:target="toggleCacheMode"
                                :disabled="! $cacheEnabled || $developmentMode"
                            >
                                Switch to {{ strtolower($cacheMode) === 'aggressive' ? 'Standard' : 'Aggressive' }}
                            </x-filament::button>
                            <x-filament::button
                                color="gray"
                                wire:click="purgeCache"
                                wire:loading.attr="disabled"
                                wire:target="purgeCache"
                                :disabled="$developmentMode"
                            >
                                Purge Cache
                            </x-filament::button>
                        </x-filament::actions>
                    </x-slot>
                </x-filament.app.settings.card>

                <x-filament.app.settings.card
                    title="Testing & Safety"
                    description="Use temporary bypass modes when you need to debug behavior without permanently changing your cache setup."
                    icon="heroicon-o-beaker"
                    :status="$troubleshootingMode ? 'Troubleshooting On' : ($developmentMode ? 'Development On' : 'Normal')"
                    :status-color="$troubleshootingMode ? 'danger' : ($developmentMode ? 'warning' : 'success')"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Development Mode', 'value' => $developmentMode ? 'Enabled (cache + optimizers bypassed)' : 'Disabled'],
                        ['label' => 'Troubleshooting Mode', 'value' => $troubleshootingMode ? 'Enabled (edge relaxed for testing)' : 'Disabled'],
                        ['label' => 'Recent Purge', 'value' => $this->lastAction(['edge.cache_purge', 'cloudfront.invalidate'])],
                        ['label' => 'Recent Mode Change', 'value' => $this->lastAction(['edge.development_mode', 'edge.troubleshooting_mode'])],
                    ]" />

                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button
                                color="{{ $developmentMode ? 'warning' : 'gray' }}"
                                wire:click="toggleDevelopmentMode"
                                wire:loading.attr="disabled"
                                wire:target="toggleDevelopmentMode"
                            >
                                {{ $developmentMode ? 'Disable Development Mode' : 'Enable Development Mode' }}
                            </x-filament::button>
                            <x-filament::button
                                color="{{ $troubleshootingMode ? 'danger' : 'gray' }}"
                                wire:click="toggleTroubleshootingMode"
                                wire:loading.attr="disabled"
                                wire:target="toggleTroubleshootingMode"
                            >
                                {{ $troubleshootingMode ? 'Disable Troubleshooting' : 'Enable Troubleshooting' }}
                            </x-filament::button>
                        </x-filament::actions>
                    </x-slot>
                </x-filament.app.settings.card>
            </div>

            @if ($this->isSimpleMode())
                <x-filament::section heading="Need More Cache Detail?" icon="heroicon-o-adjustments-horizontal">
                    <p class="text-sm">Simple mode keeps this focused. Switch to Pro for cache miss breakdown and purge history table.</p>
                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button wire:click="switchToProMode" color="gray">Switch to Pro mode</x-filament::button>
                        </x-filament::actions>
                    </x-slot>
                </x-filament::section>
            @else
            <div class="fp-protection-grid">
                <x-filament.app.settings.card title="Cache Hit Ratio Trend" description="Daily cache efficiency over the recent period." icon="heroicon-o-arrow-trending-up">
                    @if (empty($trend))
                        <p class="text-sm opacity-75">No cache trend data yet.</p>
                    @else
                        <div class="grid gap-2">
                            @foreach ($trend as $row)
                                <div class="grid gap-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span>{{ $row['day'] }}</span>
                                        <span>{{ $row['hit'] }}%</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-2 rounded-full bg-success-500" style="width: {{ $row['hit'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament.app.settings.card>

                <x-filament.app.settings.card title="Top Cache Misses" description="Paths with highest miss or error activity." icon="heroicon-o-exclamation-triangle">
                    @if (empty($misses))
                        <p class="text-sm opacity-75">No cache miss telemetry yet.</p>
                    @else
                        <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                            <table class="fi-ta-table w-full text-sm">
                                <thead>
                                    <tr class="fi-ta-header-row">
                                        <th class="fi-ta-header-cell">Path</th>
                                        <th class="fi-ta-header-cell fi-align-end">Misses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($misses as $row)
                                        <tr class="fi-ta-row">
                                            <td class="fi-ta-cell">{{ $row['path'] }}</td>
                                            <td class="fi-ta-cell fi-align-end">{{ number_format((int) $row['misses']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament.app.settings.card>
            </div>

            <x-filament.app.settings.card title="Purge History" description="Recent edge cache purge requests." icon="heroicon-o-clock">
                @if (empty($history))
                    <p class="text-sm opacity-75">No purge history yet.</p>
                @else
                    <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                        <table class="fi-ta-table w-full text-sm">
                            <thead>
                                <tr class="fi-ta-header-row">
                                    <th class="fi-ta-header-cell">Timestamp</th>
                                    <th class="fi-ta-header-cell">Result</th>
                                    <th class="fi-ta-header-cell">Purge ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $row)
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">{{ $row['timestamp']?->diffForHumans() ?? 'n/a' }}</td>
                                        <td class="fi-ta-cell">{{ ucfirst((string) $row['status']) }}</td>
                                        <td class="fi-ta-cell">{{ $row['purge_id'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament.app.settings.card>
            @endif
        @endif
    </div>
</x-filament-panels::page>
