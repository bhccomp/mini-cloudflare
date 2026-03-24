<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($siteMeta = $this->wordpressSiteMeta())
    @php($billing = $this->pluginBillingSummary())

    <div
        class="fp-protection-shell space-y-6"
        x-data
        @if ($this->shouldPollForPluginConnection()) wire:poll.10s="pollForPluginConnection" @endif
        x-on:firephage-copy-to-clipboard.window="
            const text = $event.detail.text ?? '';
            const label = $event.detail.label ?? 'Value';
            const key = $event.detail.key ?? null;
            const fallbackCopy = () => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'absolute';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            };

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text)
                        .then(() => window.dispatchEvent(new CustomEvent('firephage-copy-success', { detail: { key, label } })))
                        .catch(() => {
                            fallbackCopy();
                            window.dispatchEvent(new CustomEvent('firephage-copy-success', { detail: { key, label } }));
                        });
                } else {
                    fallbackCopy();
                    window.dispatchEvent(new CustomEvent('firephage-copy-success', { detail: { key, label } }));
                }
            } catch (error) {
                fallbackCopy();
                window.dispatchEvent(new CustomEvent('firephage-copy-success', { detail: { key, label } }));
            }
        "
    >
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @elseif (! $this->isPluginConnected())
            <x-filament::section
                heading="Connect the WordPress Plugin"
                description="Generate a one-time token here, paste it into the FirePhage Security plugin, and FirePhage will start receiving WordPress health and malware reports for this site."
                icon="heroicon-o-key"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->pluginConnectionStatusColor()">
                        {{ $this->pluginConnectionStatus() }}
                    </x-filament::badge>
                </x-slot>

                <div class="grid gap-4 md:grid-cols-3">
                    <x-filament::section compact heading="Health snapshots" description="WordPress checks, checksum posture, and update exposure will appear here once the plugin connects." />
                    <x-filament::section compact heading="Malware scans" description="The latest scan status, suspicious-file counts, and findings list will be stored on this page." />
                    <x-filament::section compact heading="Paid telemetry" description="Paid sites also unlock live firewall and performance telemetry inside the plugin and here in the dashboard." />
                </div>

                @if ($this->pluginConnectionToken)
                    <x-filament::section compact secondary>
                        <div class="space-y-2 text-sm">
                            <p class="font-medium">Connection token</p>
                            <p class="break-all font-mono">{{ $this->pluginConnectionToken }}</p>
                            <p>Expires {{ \Illuminate\Support\Carbon::parse($this->pluginConnectionTokenExpiresAt)->diffForHumans() }}</p>
                        </div>

                        <x-slot name="footer">
                            <x-filament::actions>
                                <div
                                    x-data="{ copied: false, timer: null, key: 'copy-plugin-token' }"
                                    x-on:firephage-copy-success.window="
                                        if ($event.detail.key !== key) return;
                                        copied = true;
                                        if (timer) clearTimeout(timer);
                                        timer = setTimeout(() => copied = false, 2000);
                                    "
                                >
                                    <x-filament::button
                                        type="button"
                                        color="gray"
                                        size="sm"
                                        wire:click="copyToClipboard('{{ base64_encode((string) $this->pluginConnectionToken) }}', 'Token', 'copy-plugin-token')"
                                    >
                                        <span x-show="! copied">Copy token</span>
                                        <span x-show="copied" x-cloak>Copied</span>
                                    </x-filament::button>
                                </div>
                            </x-filament::actions>
                        </x-slot>
                    </x-filament::section>
                @endif
            </x-filament::section>
        @else
            <x-filament::section
                heading="Connected WordPress Site"
                description="This page now stores the latest WordPress report from the plugin and exposes paid firewall telemetry when this site is covered by an active FirePhage subscription."
                icon="heroicon-o-command-line"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->pluginConnectionStatusColor()">
                        {{ $this->pluginConnectionStatus() }}
                    </x-filament::badge>
                </x-slot>

                <div class="grid gap-4 lg:grid-cols-2">
                    <x-filament::section compact heading="Latest report context" description="Basic environment data from the last WordPress plugin report.">
                        <div class="space-y-2 text-sm">
                            <p><strong>Home URL:</strong> {{ $siteMeta['home_url'] ?: 'Not reported' }}</p>
                            <p><strong>Site URL:</strong> {{ $siteMeta['site_url'] ?: 'Not reported' }}</p>
                            <p><strong>PHP Version:</strong> {{ $siteMeta['php_version'] ?: 'Not reported' }}</p>
                            <p><strong>Generated:</strong> {{ $siteMeta['generated_at'] ?: 'Not reported' }}</p>
                        </div>
                    </x-filament::section>

                    <x-filament::section compact heading="Firewall & performance access" description="Plugin Pro tabs read from FirePhage when this site is covered by a paid active plan.">
                        <div class="space-y-2 text-sm">
                            <p><strong>Status:</strong> {{ $billing['pro_enabled'] ?? false ? 'Enabled' : 'Plan required' }}</p>
                            <p><strong>Plan:</strong> {{ $billing['plan_name'] ?? 'Not attached yet' }}</p>
                            <p>{{ $billing['message'] ?? 'No billing state is attached to this site yet.' }}</p>
                        </div>
                    </x-filament::section>
                </div>

                @if ($this->pluginConnectionToken)
                    <x-filament::section compact secondary>
                        <div class="space-y-2 text-sm">
                            <p class="font-medium">New connection token</p>
                            <p class="break-all font-mono">{{ $this->pluginConnectionToken }}</p>
                            <p>Expires {{ \Illuminate\Support\Carbon::parse($this->pluginConnectionTokenExpiresAt)->diffForHumans() }}</p>
                        </div>
                    </x-filament::section>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
