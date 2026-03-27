<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($status = $this->originExposureStatus())
    @php($originHost = parse_url($this->site?->origin_url ?? '', PHP_URL_HOST) ?: 'Not configured')

    <style>
        .fp-origin-layout {
            display: grid;
            gap: 1.5rem;
        }

        .fp-origin-column {
            display: grid;
            gap: 1.5rem;
            align-content: start;
        }

        .fp-origin-setting-stack {
            display: grid;
            gap: 0;
        }

        .fp-origin-setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .fp-origin-setting-row:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .dark .fp-origin-setting-row {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fp-origin-setting-copy {
            display: grid;
            gap: 0.25rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .fp-origin-setting-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-origin-setting-label {
            color: rgb(248 250 252);
        }

        .fp-origin-setting-description {
            font-size: 0.875rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-origin-setting-description {
            color: rgb(148 163 184);
        }

        .fp-origin-setting-action {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex: 0 0 auto;
            text-align: right;
        }

        .fp-origin-setting-state {
            font-size: 0.8rem;
            font-weight: 600;
            color: rgb(100 116 139);
            white-space: nowrap;
        }

        .dark .fp-origin-setting-state {
            color: rgb(148 163 184);
        }

        .fp-origin-setting-action .fi-btn-group {
            display: inline-flex;
            flex-wrap: nowrap;
        }

        .fp-origin-inline-form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: min(100%, 26rem);
        }

        .fp-origin-inline-form .fi-input-wrp {
            min-width: 16rem;
        }

        .fp-origin-metric-list {
            display: grid;
            gap: 0.85rem;
        }

        .fp-origin-metric {
            display: grid;
            gap: 0.25rem;
            padding: 1rem 1.1rem;
            border-radius: 1rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(248, 250, 252, 0.82);
        }

        .dark .fp-origin-metric {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(15, 23, 42, 0.42);
        }

        .fp-origin-metric-label {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgb(100 116 139);
        }

        .dark .fp-origin-metric-label {
            color: rgb(148 163 184);
        }

        .fp-origin-metric-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: rgb(15 23 42);
        }

        .dark .fp-origin-metric-value {
            color: rgb(248 250 252);
        }

        .fp-origin-metric-support {
            font-size: 0.85rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-origin-metric-support {
            color: rgb(148 163 184);
        }

        @media (min-width: 1280px) {
            .fp-origin-layout {
                grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.9fr);
                align-items: start;
            }
        }

        @media (max-width: 768px) {
            .fp-origin-setting-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .fp-origin-setting-action,
            .fp-origin-inline-form {
                width: 100%;
                justify-content: space-between;
            }

            .fp-origin-inline-form .fi-input-wrp {
                min-width: 0;
                width: 100%;
            }
        }
    </style>

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')
            @include('filament.app.pages.protection.technical-details')

            <div class="fp-origin-layout">
                <div class="fp-origin-column">
                    <x-filament.app.settings.card
                        title="Origin Request Handling"
                        description="Control how the edge connects back to your origin and how aggressively it retries or coalesces traffic."
                        icon="heroicon-o-server-stack"
                        :status="$status"
                        :status-color="$status === 'Hardened' ? 'success' : ($status === 'Protected' ? 'warning' : 'gray')"
                    >
                        <div class="fp-origin-setting-stack">
                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Origin Host Header</div>
                                    <div class="fp-origin-setting-description">Override the host header sent upstream so your origin routes requests to the correct virtual host.</div>
                                </div>
                                <form wire:submit="saveOriginHostHeader" class="fp-origin-inline-form">
                                    <x-filament::input.wrapper>
                                        <x-filament::input
                                            wire:model.defer="originHostHeaderInput"
                                            placeholder="origin.example.com"
                                        />
                                    </x-filament::input.wrapper>
                                    <x-filament::button type="submit" color="gray">
                                        Save
                                    </x-filament::button>
                                </form>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Origin Certificate Verification</div>
                                    <div class="fp-origin-setting-description">Require the origin to present a valid SSL certificate before the edge will fetch from it over HTTPS.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <x-filament::button.group>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originSslVerificationEnabled() ? 'primary' : 'gray' }}" wire:click="setOriginSslVerificationState(true)">
                                            Enabled
                                        </x-filament::button>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originSslVerificationEnabled() ? 'gray' : 'danger' }}" wire:click="setOriginSslVerificationState(false)">
                                            Disabled
                                        </x-filament::button>
                                    </x-filament::button.group>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Request Coalescing</div>
                                    <div class="fp-origin-setting-description">Merge concurrent requests for the same uncached resource so the origin only handles one fetch while others wait.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <x-filament::button.group>
                                        <x-filament::button type="button" size="xs" color="{{ $this->requestCoalescingEnabled() ? 'primary' : 'gray' }}" wire:click="setRequestCoalescingState(true)">
                                            Enabled
                                        </x-filament::button>
                                        <x-filament::button type="button" size="xs" color="{{ $this->requestCoalescingEnabled() ? 'gray' : 'danger' }}" wire:click="setRequestCoalescingState(false)">
                                            Disabled
                                        </x-filament::button>
                                    </x-filament::button.group>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Coalescing Timeout</div>
                                    <div class="fp-origin-setting-description">How long queued requests should wait for the first origin fetch to complete before timing out.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <span class="fp-origin-setting-state">{{ $this->requestCoalescingTimeout() }}s</span>
                                    <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                                        <x-slot name="trigger">
                                            <x-filament::button color="gray">Change</x-filament::button>
                                        </x-slot>
                                        <x-filament::dropdown.list>
                                            @foreach ([5, 10, 15, 30, 60] as $seconds)
                                                <x-filament::dropdown.list.item tag="button" type="button" wire:click="setRequestCoalescingTimeout({{ $seconds }})">
                                                    {{ $seconds }} seconds
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Origin Retries</div>
                                    <div class="fp-origin-setting-description">How many times the edge should retry the origin before failing the request.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <span class="fp-origin-setting-state">{{ $this->originRetries() }} {{ $this->originRetries() === 1 ? 'retry' : 'retries' }}</span>
                                    <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                                        <x-slot name="trigger">
                                            <x-filament::button color="gray">Change</x-filament::button>
                                        </x-slot>
                                        <x-filament::dropdown.list>
                                            @foreach ([0, 1, 2, 3, 4, 5] as $retries)
                                                <x-filament::dropdown.list.item tag="button" type="button" wire:click="setOriginRetries({{ $retries }})">
                                                    {{ $retries }} {{ $retries === 1 ? 'retry' : 'retries' }}
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Connect Timeout</div>
                                    <div class="fp-origin-setting-description">How long the edge waits when opening a connection to the origin.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <span class="fp-origin-setting-state">{{ $this->originConnectTimeout() }}s</span>
                                    <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                                        <x-slot name="trigger">
                                            <x-filament::button color="gray">Change</x-filament::button>
                                        </x-slot>
                                        <x-filament::dropdown.list>
                                            @foreach ([5, 10, 15, 30, 60] as $seconds)
                                                <x-filament::dropdown.list.item tag="button" type="button" wire:click="setOriginConnectTimeout({{ $seconds }})">
                                                    {{ $seconds }} seconds
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Response Timeout</div>
                                    <div class="fp-origin-setting-description">How long the edge waits for the origin to return data once the connection is open.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <span class="fp-origin-setting-state">{{ $this->originResponseTimeout() }}s</span>
                                    <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                                        <x-slot name="trigger">
                                            <x-filament::button color="gray">Change</x-filament::button>
                                        </x-slot>
                                        <x-filament::dropdown.list>
                                            @foreach ([15, 30, 60, 120, 300] as $seconds)
                                                <x-filament::dropdown.list.item tag="button" type="button" wire:click="setOriginResponseTimeout({{ $seconds }})">
                                                    {{ $seconds }} seconds
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Retry Delay</div>
                                    <div class="fp-origin-setting-description">Add a short pause between retry attempts so the origin has a chance to recover.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <span class="fp-origin-setting-state">{{ $this->originRetryDelay() }}s</span>
                                    <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                                        <x-slot name="trigger">
                                            <x-filament::button color="gray">Change</x-filament::button>
                                        </x-slot>
                                        <x-filament::dropdown.list>
                                            @foreach ([0, 1, 2, 5, 10] as $seconds)
                                                <x-filament::dropdown.list.item tag="button" type="button" wire:click="setOriginRetryDelay({{ $seconds }})">
                                                    {{ $seconds }} seconds
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="Resilience & Recovery"
                        description="Define which origin failures should trigger retries or serve stale content while the backend recovers."
                        icon="heroicon-o-lifebuoy"
                    >
                        <div class="fp-origin-setting-stack">
                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Retry 5xx Responses</div>
                                    <div class="fp-origin-setting-description">Retry the origin when it returns a server-side failure like 502, 503, or 504.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <x-filament::button.group>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originRetry5xxEnabled() ? 'primary' : 'gray' }}" wire:click="setOriginRetry5xxState(true)">
                                            Enabled
                                        </x-filament::button>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originRetry5xxEnabled() ? 'gray' : 'danger' }}" wire:click="setOriginRetry5xxState(false)">
                                            Disabled
                                        </x-filament::button>
                                    </x-filament::button.group>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Retry Connection Timeouts</div>
                                    <div class="fp-origin-setting-description">Retry the origin when the connection cannot be opened in time.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <x-filament::button.group>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originRetryConnectionTimeoutEnabled() ? 'primary' : 'gray' }}" wire:click="setOriginRetryConnectionTimeoutState(true)">
                                            Enabled
                                        </x-filament::button>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originRetryConnectionTimeoutEnabled() ? 'gray' : 'danger' }}" wire:click="setOriginRetryConnectionTimeoutState(false)">
                                            Disabled
                                        </x-filament::button>
                                    </x-filament::button.group>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Retry Response Timeouts</div>
                                    <div class="fp-origin-setting-description">Retry the origin when it accepts the connection but takes too long to return a response.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <x-filament::button.group>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originRetryResponseTimeoutEnabled() ? 'primary' : 'gray' }}" wire:click="setOriginRetryResponseTimeoutState(true)">
                                            Enabled
                                        </x-filament::button>
                                        <x-filament::button type="button" size="xs" color="{{ $this->originRetryResponseTimeoutEnabled() ? 'gray' : 'danger' }}" wire:click="setOriginRetryResponseTimeoutState(false)">
                                            Disabled
                                        </x-filament::button>
                                    </x-filament::button.group>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Stale While Updating</div>
                                    <div class="fp-origin-setting-description">Serve the last good cached response while the edge refreshes content from the origin in the background.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <x-filament::button.group>
                                        <x-filament::button type="button" size="xs" color="{{ $this->staleWhileUpdatingEnabled() ? 'primary' : 'gray' }}" wire:click="setStaleWhileUpdatingState(true)">
                                            Enabled
                                        </x-filament::button>
                                        <x-filament::button type="button" size="xs" color="{{ $this->staleWhileUpdatingEnabled() ? 'gray' : 'danger' }}" wire:click="setStaleWhileUpdatingState(false)">
                                            Disabled
                                        </x-filament::button>
                                    </x-filament::button.group>
                                </div>
                            </div>

                            <div class="fp-origin-setting-row">
                                <div class="fp-origin-setting-copy">
                                    <div class="fp-origin-setting-label">Stale While Offline</div>
                                    <div class="fp-origin-setting-description">Keep serving the last known good cached response when the origin is temporarily unreachable.</div>
                                </div>
                                <div class="fp-origin-setting-action">
                                    <x-filament::button.group>
                                        <x-filament::button type="button" size="xs" color="{{ $this->staleWhileOfflineEnabled() ? 'primary' : 'gray' }}" wire:click="setStaleWhileOfflineState(true)">
                                            Enabled
                                        </x-filament::button>
                                        <x-filament::button type="button" size="xs" color="{{ $this->staleWhileOfflineEnabled() ? 'gray' : 'danger' }}" wire:click="setStaleWhileOfflineState(false)">
                                            Disabled
                                        </x-filament::button>
                                    </x-filament::button.group>
                                </div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>
                </div>

                <div class="fp-origin-column">
                    <x-filament.app.settings.card
                        title="Origin Snapshot"
                        description="Live upstream connection posture for this site."
                        icon="heroicon-o-bolt"
                    >
                        <div class="fp-origin-metric-list">
                            <div class="fp-origin-metric">
                                <div class="fp-origin-metric-label">Exposure Status</div>
                                <div class="fp-origin-metric-value">{{ $status }}</div>
                                <div class="fp-origin-metric-support">A quick summary of how safely the edge is interacting with the origin right now.</div>
                            </div>
                            <div class="fp-origin-metric">
                                <div class="fp-origin-metric-label">Origin Host</div>
                                <div class="fp-origin-metric-value">{{ $originHost }}</div>
                                <div class="fp-origin-metric-support">The upstream hostname currently configured for this site.</div>
                            </div>
                            <div class="fp-origin-metric">
                                <div class="fp-origin-metric-label">Host Header</div>
                                <div class="fp-origin-metric-value">{{ $this->originHostHeader() }}</div>
                                <div class="fp-origin-metric-support">The hostname the edge presents to the origin on each request.</div>
                            </div>
                            <div class="fp-origin-metric">
                                <div class="fp-origin-metric-label">Observed Latency</div>
                                <div class="fp-origin-metric-value">{{ $this->originLatency() }}</div>
                                <div class="fp-origin-metric-support">Recent origin round-trip time reported by FirePhage telemetry.</div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="What These Controls Cover"
                        description="How the Origin page settings affect upstream reliability."
                        icon="heroicon-o-information-circle"
                    >
                        <div class="grid gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <p><strong class="text-slate-900 dark:text-slate-100">Request handling:</strong> Host header, connection limits, and coalescing decide how efficiently the edge talks to your upstream service.</p>
                            <p><strong class="text-slate-900 dark:text-slate-100">Retry policy:</strong> Retry controls define when the edge should try the origin again before surfacing a failure to visitors.</p>
                            <p><strong class="text-slate-900 dark:text-slate-100">Resilience:</strong> Stale response settings help keep the site available while the origin is slow, busy, or briefly unavailable.</p>
                            <p><strong class="text-slate-900 dark:text-slate-100">Certificate trust:</strong> Origin certificate verification ensures the upstream TLS certificate is valid before the edge fetches protected content.</p>
                        </div>
                    </x-filament.app.settings.card>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
