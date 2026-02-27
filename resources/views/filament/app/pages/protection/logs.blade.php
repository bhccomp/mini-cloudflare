<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($rows = $this->filteredLogEntries())

    <div class="fp-protection-shell">
        @include('filament.app.pages.protection.technical-details')

        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @elseif ($this->isSimpleMode())
            @php($activity = app(\App\Services\ActivityFeedService::class)->forSite($this->site, 5))
            <x-filament::section heading="Recent Activity" description="Human-friendly summary of recent protection events." icon="heroicon-o-bolt">
                <div class="space-y-3">
                    @foreach ($activity as $item)
                        <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                            <p>{{ $item['message'] }}</p>
                            @if ($item['at'])
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['at']->diffForHumans() }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button wire:click="switchToProMode" color="gray">Switch to Pro for full logs</x-filament::button>
                    </x-filament::actions>
                </x-slot>
            </x-filament::section>
        @else
            <x-filament::section heading="Live Event Logs" description="Request and protection events from the edge network." icon="heroicon-o-document-text">
                <div class="grid gap-3 md:grid-cols-4">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="timeRange">
                            <option value="1h">Last 1 hour</option>
                            <option value="6h">Last 6 hours</option>
                            <option value="24h">Last 24 hours</option>
                            <option value="7d">Last 7 days</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="statusGroup">
                            <option value="">All statuses</option>
                            <option value="2xx">2xx</option>
                            <option value="3xx">3xx</option>
                            <option value="4xx">4xx</option>
                            <option value="5xx">5xx</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="country">
                            <option value="">All countries</option>
                            @foreach ($this->countries() as $country)
                                <option value="{{ $country }}">{{ $country }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="suspiciousOnly" class="rounded border-gray-300" />
                        Suspicious only
                    </label>
                </div>

                @if (empty($rows))
                    <div class="grid gap-2 text-sm opacity-80">
                        <p><strong>No traffic yet:</strong> once requests hit the edge network, events will appear here.</p>
                        <p><strong>Logging not enabled:</strong> event ingestion is not active for this site yet.</p>
                        <p><strong>Edge delay:</strong> live logs can appear with a short delay after refresh.</p>
                    </div>
                @else
                    <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                        <table class="fi-ta-table w-full text-sm">
                            <thead>
                                <tr class="fi-ta-header-row">
                                    <th class="fi-ta-header-cell">Timestamp</th>
                                    <th class="fi-ta-header-cell">Client IP</th>
                                    <th class="fi-ta-header-cell">Country</th>
                                    <th class="fi-ta-header-cell">Method</th>
                                    <th class="fi-ta-header-cell">Host</th>
                                    <th class="fi-ta-header-cell">Path</th>
                                    <th class="fi-ta-header-cell">Status</th>
                                    <th class="fi-ta-header-cell">Cache Status</th>
                                    <th class="fi-ta-header-cell">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php($host = parse_url((string) $row['uri'], PHP_URL_HOST) ?: $this->site->apex_domain)
                                    @php($path = parse_url((string) $row['uri'], PHP_URL_PATH) ?: (string) $row['uri'])
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">{{ \Illuminate\Support\Carbon::parse($row['timestamp'])->diffForHumans() }}</td>
                                        <td class="fi-ta-cell">{{ $row['ip'] }}</td>
                                        <td class="fi-ta-cell">{{ $row['country'] }}</td>
                                        <td class="fi-ta-cell">{{ $row['method'] }}</td>
                                        <td class="fi-ta-cell">{{ $host }}</td>
                                        <td class="fi-ta-cell">{{ $path }}</td>
                                        <td class="fi-ta-cell">{{ (int) ($row['status_code'] ?? 0) }}</td>
                                        <td class="fi-ta-cell">{{ strtoupper((string) ($row['rule'] ?? 'n/a')) }}</td>
                                        <td class="fi-ta-cell">{{ $row['action'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <x-slot name="footer">
                    <div class="flex items-center justify-between gap-2">
                        <x-filament::button color="gray" wire:click="refreshLogs" wire:loading.attr="disabled" wire:target="refreshLogs">Refresh logs</x-filament::button>
                        <div class="flex items-center gap-2">
                            <x-filament::button color="gray" wire:click="prevLogsPage" wire:loading.attr="disabled" wire:target="prevLogsPage">Previous</x-filament::button>
                            <x-filament::button color="gray" wire:click="nextLogsPage" wire:loading.attr="disabled" wire:target="nextLogsPage">Next</x-filament::button>
                        </div>
                    </div>
                </x-slot>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
