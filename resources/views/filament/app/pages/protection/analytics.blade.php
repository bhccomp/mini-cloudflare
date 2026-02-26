<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($m = $this->site?->analyticsMetric)
    @php($requestTrend = $this->requestTrend())
    @php($blockTrend = $this->blockRatioTrend())
    @php($heatmap = $this->requestsPerHourHeatmap())
    @php($maxRequests = max(1, (int) collect($requestTrend)->max('total')))
    @php($maxHeat = max(1, (int) collect($heatmap)->max('count')))

    <div class="fp-protection-shell">
        @include('filament.app.pages.protection.technical-details')

        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section heading="Live Telemetry" description="Traffic trends, threat ratio, and hourly load." icon="heroicon-o-chart-bar-square">
                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button color="gray" wire:click="refreshAnalytics" wire:loading.attr="disabled" wire:target="refreshAnalytics">Refresh telemetry</x-filament::button>
                    </x-filament::actions>
                </x-slot>

                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'Requests (24h)', 'value' => number_format((int) ($m->total_requests_24h ?? 0))],
                    ['label' => 'Blocked (24h)', 'value' => number_format((int) ($m->blocked_requests_24h ?? 0))],
                    ['label' => 'Block Ratio', 'value' => $m && (int) ($m->total_requests_24h ?? 0) > 0 ? number_format(((float) ($m->blocked_requests_24h ?? 0) / (float) max(1, (int) ($m->total_requests_24h ?? 0))) * 100, 2) . '%' : 'No telemetry yet'],
                    ['label' => 'Telemetry Status', 'value' => $this->telemetryStatus()],
                    ['label' => 'Last Telemetry Sync', 'value' => $m?->captured_at?->diffForHumans() ?: 'Never'],
                ]" />
            </x-filament::section>

            <div class="fp-protection-grid">
                <x-filament::section heading="Requests Trend (7d)" description="Daily request volume trend." icon="heroicon-o-arrow-trending-up">
                    @if (empty($requestTrend))
                        <p class="text-sm opacity-75">No request trend data yet.</p>
                    @else
                        <div class="grid gap-2">
                            @foreach ($requestTrend as $row)
                                @php($width = max(3, (int) round(((int) $row['total'] / $maxRequests) * 100)))
                                <div class="grid gap-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span>{{ $row['label'] }}</span>
                                        <span>{{ number_format((int) $row['total']) }}</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-primary-500" style="width: {{ $width }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Block Ratio Trend" description="Daily block pressure over seven days." icon="heroicon-o-shield-exclamation">
                    @if (empty($blockTrend))
                        <p class="text-sm opacity-75">No block ratio trend data yet.</p>
                    @else
                        <div class="grid gap-2">
                            @foreach ($blockTrend as $row)
                                <div class="grid gap-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span>{{ $row['label'] }}</span>
                                        <span>{{ number_format((float) $row['ratio'], 2) }}%</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-red-500" style="width: {{ max(2, (int) round((float) $row['ratio'])) }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            </div>

            <x-filament::section heading="Requests Per Hour (24h)" description="Hourly request heatmap for the last 24 hours." icon="heroicon-o-squares-2x2">
                <div class="grid grid-cols-6 gap-2 md:grid-cols-12">
                    @foreach ($heatmap as $cell)
                        @php($alpha = 0.12 + (((int) $cell['count']) / $maxHeat) * 0.88)
                        <div class="rounded-lg border border-gray-200 px-2 py-2 text-center text-xs dark:border-gray-800" style="background: rgba(30, 64, 175, {{ number_format($alpha, 3, '.', '') }}); color: {{ $alpha > 0.55 ? '#ffffff' : '#0f172a' }};">
                            <div class="font-medium">{{ $cell['hour'] }}h</div>
                            <div>{{ number_format((int) $cell['count']) }}</div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
