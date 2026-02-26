<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($trend = $this->cacheHitTrend())
    @php($misses = $this->topCacheMisses())
    @php($history = $this->purgeHistory())

    <div class="fp-protection-shell">
        @include('filament.app.pages.protection.technical-details')

        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section heading="Cache Control" description="Edge caching state and cache efficiency." icon="heroicon-o-circle-stack">
                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button color="gray" wire:click="purgeCache" wire:loading.attr="disabled" wire:target="purgeCache">Purge cache</x-filament::button>
                    </x-filament::actions>
                </x-slot>

                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'Cache Policy', 'value' => 'Managed by Edge Network'],
                    ['label' => 'Cache Hit Ratio', 'value' => $this->metricCacheHitRatio()],
                    ['label' => 'Last Action', 'value' => $this->lastAction('cloudfront.invalidate')],
                    ['label' => 'Last Sync', 'value' => $this->site->analyticsMetric?->captured_at?->diffForHumans() ?: 'Not synced yet'],
                ]" />
            </x-filament::section>

            <div class="fp-protection-grid">
                <x-filament::section heading="Cache Hit Ratio Trend" description="Daily cache efficiency over the recent period." icon="heroicon-o-arrow-trending-up">
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
                </x-filament::section>

                <x-filament::section heading="Top Cache Misses" description="Paths with highest miss/error activity." icon="heroicon-o-exclamation-triangle">
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
                </x-filament::section>
            </div>

            <x-filament::section heading="Purge History" description="Recent edge cache purge requests." icon="heroicon-o-clock">
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
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
