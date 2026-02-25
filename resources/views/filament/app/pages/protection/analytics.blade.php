<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @php($m = $this->site->analyticsMetric)
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="Analytics"
                        description="Traffic and threat intelligence for this site."
                        icon="heroicon-o-chart-bar-square"
                        :status="$m ? 'Live data' : 'No data yet'"
                        :status-color="$m ? 'success' : 'gray'"
                    >
                        <x-filament.app.settings.section title="Traffic Intelligence" description="Provider-aware metrics (AWS/Bunny)">
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Provider', 'value' => strtoupper((string) $this->site->provider)],
                                ['label' => 'Total Requests (24h)', 'value' => number_format((int) ($m->total_requests_24h ?? 0))],
                                ['label' => 'Blocked (24h)', 'value' => number_format((int) ($m->blocked_requests_24h ?? 0))],
                                ['label' => 'Allowed (24h)', 'value' => number_format((int) ($m->allowed_requests_24h ?? 0))],
                                ['label' => 'Cache Hit Ratio', 'value' => $m && $m->cache_hit_ratio !== null ? number_format((float) $m->cache_hit_ratio, 2) . '%' : 'N/A'],
                                ['label' => 'Captured At', 'value' => $m?->captured_at?->diffForHumans() ?: 'Not captured'],
                            ]" />

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button color="gray" wire:click="refreshAnalytics">Refresh analytics</x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card title="Trend" description="Last 7 days" icon="heroicon-o-arrow-trending-up">
                    <x-filament.app.settings.section title="Traffic Trend" description="Allowed vs blocked requests by day">
                        @if (! $m || empty($m->trend_labels))
                            <p class="text-sm opacity-75">No trend data yet. Click refresh after traffic reaches the edge.</p>
                        @else
                            <x-filament.app.settings.key-value-grid :rows="collect($m->trend_labels)->map(function ($label, $idx) use ($m) {
                                return [
                                    'label' => $label,
                                    'value' => 'Allowed: ' . number_format((int) (($m->allowed_trend[$idx] ?? 0))) . ' | Blocked: ' . number_format((int) (($m->blocked_trend[$idx] ?? 0))),
                                ];
                            })->values()->all()" />
                        @endif
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
