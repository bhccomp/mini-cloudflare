<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />
    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @php($rows = $this->logEntries())
            <div class="fp-protection-grid">
                <div>
                    <x-filament.app.settings.card
                        title="Logs"
                        description="Security events and platform activity stream."
                        icon="heroicon-o-document-text"
                        :status="count($rows) > 0 ? 'Live stream' : 'No events yet'"
                        :status-color="count($rows) > 0 ? 'success' : 'gray'"
                    >
                        <x-filament.app.settings.section title="Event Stream" description="Provider-aware request and security events">
                            @if (empty($rows))
                                <p class="text-sm opacity-75">No log events yet. Traffic must pass through edge before events appear.</p>
                            @else
                                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                                    <table class="fi-ta-table w-full text-sm">
                                        <thead>
                                            <tr class="fi-ta-header-row">
                                                <th class="fi-ta-header-cell">Time</th>
                                                <th class="fi-ta-header-cell">Action</th>
                                                <th class="fi-ta-header-cell">Country</th>
                                                <th class="fi-ta-header-cell">IP</th>
                                                <th class="fi-ta-header-cell">Request</th>
                                                <th class="fi-ta-header-cell">Rule / Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rows as $row)
                                                <tr class="fi-ta-row">
                                                    <td class="fi-ta-cell">{{ \Illuminate\Support\Carbon::parse($row['timestamp'])->diffForHumans() }}</td>
                                                    <td class="fi-ta-cell">{{ $row['action'] }}</td>
                                                    <td class="fi-ta-cell">{{ $row['country'] }}</td>
                                                    <td class="fi-ta-cell">{{ $row['ip'] }}</td>
                                                    <td class="fi-ta-cell">{{ $row['method'] }} {{ $row['uri'] }}</td>
                                                    <td class="fi-ta-cell">{{ $row['rule'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <x-slot name="actions">
                                <x-filament.app.settings.action-row>
                                    <x-filament::button color="gray" wire:click="refreshLogs">Refresh logs</x-filament::button>
                                </x-filament.app.settings.action-row>
                            </x-slot>
                        </x-filament.app.settings.section>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card title="Stream Metadata" description="Current provider and ingestion mode" icon="heroicon-o-information-circle">
                    <x-filament.app.settings.section title="Log Source" description="Data source currently backing this table">
                        <x-filament.app.settings.key-value-grid :rows="[
                            ['label' => 'Provider', 'value' => strtoupper((string) $this->site->provider)],
                            ['label' => 'Rows shown', 'value' => number_format(count($rows))],
                            ['label' => 'Mode', 'value' => $this->site->provider === 'bunny' ? 'Bunny edge logs' : 'Platform audit stream'],
                        ]" />
                    </x-filament.app.settings.section>
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
