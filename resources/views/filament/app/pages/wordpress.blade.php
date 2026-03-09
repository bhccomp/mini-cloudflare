<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($report = $this->latestPluginReport())
    @php($health = $this->wordpressHealthSummary())
    @php($scan = $this->wordpressScanSummary())
    @php($siteMeta = $this->wordpressSiteMeta())

    <div class="fp-protection-shell space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @elseif (! $this->isPluginConnected())
            <x-filament::section
                heading="WordPress Plugin Connection"
                description="Generate a one-time token for the FirePhage Security plugin, then paste it into the plugin's Connect tab."
                icon="heroicon-o-key"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->pluginConnectionStatusColor()">
                        {{ $this->pluginConnectionStatus() }}
                    </x-filament::badge>
                </x-slot>

                <div class="space-y-2 text-sm">
                    <p>Use this only for the site currently selected in FirePhage.</p>
                    <p>The token expires after 15 minutes and can only be used once.</p>
                    <p>Generating a new token replaces any previous unused token for this site.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-3 mt-4">
                    <div class="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
                        <p class="font-medium">WordPress health status</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">HTTPS, file editor, XML-RPC, registration, default admin, and checksum posture.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
                        <p class="font-medium">Malware scan results</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">Latest scan status, suspicious file count, findings summary, and last report time.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
                        <p class="font-medium">Update exposure</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">Core, plugin, and theme update counts plus inactive plugin exposure from the site report.</p>
                    </div>
                </div>

                @if ($this->pluginConnectionToken)
                    <x-filament::section compact secondary class="mt-4">
                        <p><strong>Connection token</strong></p>
                        <p class="break-all font-mono text-sm">{{ $this->pluginConnectionToken }}</p>
                        <p><strong>Expires:</strong> {{ \Illuminate\Support\Carbon::parse($this->pluginConnectionTokenExpiresAt)->diffForHumans() }}</p>
                        <x-slot name="footer">
                            <x-filament::actions>
                                <x-filament::button
                                    color="gray"
                                    size="sm"
                                    x-on:click="navigator.clipboard.writeText(@js($this->pluginConnectionToken))"
                                >
                                    Copy token
                                </x-filament::button>
                            </x-filament::actions>
                        </x-slot>
                    </x-filament::section>
                @endif

                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button
                            color="gray"
                            wire:click="generatePluginToken"
                            wire:loading.attr="disabled"
                            wire:target="generatePluginToken"
                        >
                            Generate token
                        </x-filament::button>
                    </x-filament::actions>
                </x-slot>
            </x-filament::section>
        @else
            <x-filament::section
                heading="Connected WordPress Site"
                description="FirePhage is receiving reports from the plugin installed on this site."
                icon="heroicon-o-command-line"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->pluginConnectionStatusColor()">
                        {{ $this->pluginConnectionStatus() }}
                    </x-filament::badge>
                </x-slot>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Last seen</div>
                        <div class="mt-2 text-xl font-semibold">{{ $this->pluginConnectionLastSeen() }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Last report</div>
                        <div class="mt-2 text-xl font-semibold">{{ $this->pluginConnectionLastReported() }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">WordPress</div>
                        <div class="mt-2 text-xl font-semibold">{{ $siteMeta['wp_version'] ?: '--' }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Plugin</div>
                        <div class="mt-2 text-xl font-semibold">{{ $siteMeta['plugin_version'] ?: '--' }}</div>
                    </div>
                </div>

                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button
                            color="gray"
                            wire:click="generatePluginToken"
                            wire:loading.attr="disabled"
                            wire:target="generatePluginToken"
                        >
                            Generate new token
                        </x-filament::button>
                    </x-filament::actions>
                </x-slot>
            </x-filament::section>

            @if ($this->pluginConnectionToken)
                <x-filament::section compact secondary>
                    <p><strong>New connection token</strong></p>
                    <p class="break-all font-mono text-sm">{{ $this->pluginConnectionToken }}</p>
                    <p><strong>Expires:</strong> {{ \Illuminate\Support\Carbon::parse($this->pluginConnectionTokenExpiresAt)->diffForHumans() }}</p>
                </x-filament::section>
            @endif

            <div class="grid gap-6 xl:grid-cols-2">
                <x-filament::section heading="WordPress Health Status" description="Last health snapshot received from the plugin." icon="heroicon-o-heart">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Checks Passing</div>
                            <div class="mt-2 text-2xl font-semibold">{{ $health['good'] }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Warnings</div>
                            <div class="mt-2 text-2xl font-semibold">{{ $health['warning'] }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Critical</div>
                            <div class="mt-2 text-2xl font-semibold">{{ $health['critical'] }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Core Checksums</div>
                            <div class="mt-2 text-lg font-semibold">{{ ucfirst($health['checksum_status']) }}</div>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">{{ $health['checksum_summary'] }}</p>
                </x-filament::section>

                <x-filament::section heading="Malware Scan" description="Latest background scan state reported by the plugin." icon="heroicon-o-shield-exclamation">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Status</div>
                            <div class="mt-2 text-lg font-semibold">{{ ucfirst($scan['status']) }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Files Scanned</div>
                            <div class="mt-2 text-2xl font-semibold">{{ number_format($scan['scanned_files']) }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Suspicious</div>
                            <div class="mt-2 text-2xl font-semibold">{{ number_format($scan['suspicious_files']) }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Skipped</div>
                            <div class="mt-2 text-2xl font-semibold">{{ number_format($scan['skipped_files']) }}</div>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        Last scan update:
                        {{ $scan['finished_at'] ? \Illuminate\Support\Carbon::parse($scan['finished_at'])->diffForHumans() : ($scan['updated_at'] ? \Illuminate\Support\Carbon::parse($scan['updated_at'])->diffForHumans() : 'Not reported yet') }}
                    </p>
                </x-filament::section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <x-filament::section heading="Update Exposure" description="Version and maintenance posture from the latest plugin report." icon="heroicon-o-arrow-path">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Core Updates</div>
                            <div class="mt-2 text-2xl font-semibold">{{ $health['core_updates'] }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Plugin Updates</div>
                            <div class="mt-2 text-2xl font-semibold">{{ $health['plugin_updates'] }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Theme Updates</div>
                            <div class="mt-2 text-2xl font-semibold">{{ $health['theme_updates'] }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Inactive Plugins</div>
                            <div class="mt-2 text-2xl font-semibold">{{ $health['inactive_plugins'] }}</div>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Latest Report Context" description="Basic environment details reported by the connected plugin." icon="heroicon-o-information-circle">
                    <div class="space-y-2 text-sm">
                        <p><strong>Home URL:</strong> {{ $siteMeta['home_url'] ?: 'Not reported' }}</p>
                        <p><strong>Site URL:</strong> {{ $siteMeta['site_url'] ?: 'Not reported' }}</p>
                        <p><strong>PHP Version:</strong> {{ $siteMeta['php_version'] ?: 'Not reported' }}</p>
                        <p><strong>Generated:</strong> {{ $siteMeta['generated_at'] ?: 'Not reported' }}</p>
                    </div>
                </x-filament::section>
            </div>

            <x-filament::section heading="Latest Malware Findings" description="Recent suspicious or integrity findings received from the plugin." icon="heroicon-o-bug-ant">
                @php($findings = $scan['findings'])
                @if (empty($findings))
                    <p class="text-sm text-gray-600 dark:text-gray-300">No recent findings were included in the latest plugin report.</p>
                @else
                    <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                        <table class="fi-ta-table w-full text-sm">
                            <thead>
                                <tr class="fi-ta-header-row">
                                    <th class="fi-ta-header-cell">File</th>
                                    <th class="fi-ta-header-cell">Type</th>
                                    <th class="fi-ta-header-cell">Confidence</th>
                                    <th class="fi-ta-header-cell">Reasons</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($findings as $finding)
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">{{ data_get($finding, 'file', '--') }}</td>
                                        <td class="fi-ta-cell">{{ ucfirst((string) data_get($finding, 'type', 'review')) }}</td>
                                        <td class="fi-ta-cell">{{ ucfirst((string) data_get($finding, 'confidence', 'n/a')) }}</td>
                                        <td class="fi-ta-cell">{{ implode(', ', (array) data_get($finding, 'reasons', [])) ?: 'No reasons provided' }}</td>
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
