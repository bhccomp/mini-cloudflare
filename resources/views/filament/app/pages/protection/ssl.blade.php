<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($sslHostnames = $this->sslHostnames())
    @php($certificateStatus = $this->certificateStatus())

    <style>
        .fp-ssl-layout {
            display: grid;
            gap: 1.5rem;
        }

        .fp-ssl-row {
            display: grid;
            gap: 1.5rem;
            align-items: stretch;
        }

        .fp-ssl-row > * {
            height: 100%;
        }

        .fp-ssl-row .fp-pro-card {
            height: 100%;
        }

        .fp-setting-stack {
            display: grid;
            gap: 0;
        }

        .fp-setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .dark .fp-setting-row {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fp-setting-row:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .fp-setting-copy {
            display: grid;
            gap: 0.25rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .fp-setting-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-setting-label {
            color: rgb(248 250 252);
        }

        .fp-setting-description {
            font-size: 0.875rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-setting-description {
            color: rgb(148 163 184);
        }

        .fp-setting-action {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex: 0 0 auto;
            text-align: right;
        }

        .fp-setting-state {
            font-size: 0.8rem;
            font-weight: 600;
            color: rgb(100 116 139);
            white-space: nowrap;
        }

        .dark .fp-setting-state {
            color: rgb(148 163 184);
        }

        .fp-ssl-hostname-table .fi-ta-row {
            height: 3.5rem;
        }

        @media (min-width: 1280px) {
            .fp-ssl-row {
                grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.95fr);
            }
        }

        @media (max-width: 768px) {
            .fp-setting-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .fp-setting-action {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')

            <div class="fp-ssl-layout">
                <div class="fp-ssl-row">
                    <x-filament.app.settings.card
                        title="SSL / TLS"
                        description="Certificate posture, TLS state, and HTTPS delivery health for the selected site."
                        icon="heroicon-o-lock-closed"
                        :status="$certificateStatus"
                        :status-color="$certificateStatus === 'Active' ? 'success' : 'warning'"
                    >
                        <div class="fp-setting-stack">
                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Certificate Status</div>
                                    <div class="fp-setting-description">Live certificate state for the selected hostname set.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $certificateStatus }}</span>
                                    <x-filament::button color="gray" wire:click="refreshSslStatus" wire:loading.attr="disabled" wire:target="refreshSslStatus">
                                        Refresh SSL Status
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">SSL Management</div>
                                    <div class="fp-setting-description">Shows whether certificates are being handled automatically by the active edge provider.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->sslManagedBy() }} · {{ $this->sslAutoModeLabel() }}</span>
                                    <x-filament::button wire:click="requestSsl" wire:loading.attr="disabled" wire:target="requestSsl">
                                        {{ $this->site->provider === \App\Models\Site::PROVIDER_BUNNY ? 'Refresh Certificates' : 'Request Certificate' }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Origin Certificate Verification</div>
                                    <div class="fp-setting-description">When enabled, the edge requires a valid SSL certificate on your origin before it will fetch over HTTPS. When disabled, HTTPS to origin is still allowed, but certificate trust is relaxed.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->sslOriginVerificationLabel() }}</span>
                                    <x-filament::button
                                        color="{{ $this->originSslVerificationEnabled() ? 'warning' : 'gray' }}"
                                        wire:click="toggleOriginSslVerification"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleOriginSslVerification"
                                    >
                                        {{ $this->originSslVerificationEnabled() ? 'Disable Strict Origin Check' : 'Enable Strict Origin Check' }}
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="TLS Compatibility"
                        description="Reduce legacy protocol exposure while keeping the minimum client compatibility you still need."
                        icon="heroicon-o-shield-check"
                        :status="collect([$this->tls10Enabled(), $this->tls11Enabled()])->filter()->isEmpty() ? 'Modern only' : 'Legacy compatibility enabled'"
                        :status-color="collect([$this->tls10Enabled(), $this->tls11Enabled()])->filter()->isEmpty() ? 'success' : 'warning'"
                    >
                        <div class="fp-setting-stack">
                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">TLS 1.0</div>
                                    <div class="fp-setting-description">Older protocol support for legacy clients. Keeping this disabled is the safer default.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->tls10Enabled() ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $this->tls10Enabled() ? 'warning' : 'gray' }}"
                                        wire:click="toggleTls10"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleTls10"
                                    >
                                        {{ $this->tls10Enabled() ? 'Disable TLS 1.0' : 'Enable TLS 1.0' }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">TLS 1.1</div>
                                    <div class="fp-setting-description">Compatibility mode for older clients. Keeping this disabled is also the safer default.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->tls11Enabled() ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $this->tls11Enabled() ? 'warning' : 'gray' }}"
                                        wire:click="toggleTls11"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleTls11"
                                    >
                                        {{ $this->tls11Enabled() ? 'Disable TLS 1.1' : 'Enable TLS 1.1' }}
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>
                </div>

                <div class="fp-ssl-row">
                    <x-filament.app.settings.card
                        title="Hostname Certificates"
                        description="Per-hostname certificate status for the domains attached to this site."
                        icon="heroicon-o-globe-alt"
                    >
                        @if ($sslHostnames === [])
                            <p class="text-sm opacity-75">No hostname certificate details are available yet. Refresh SSL status after the edge hostname is attached.</p>
                        @else
                            <div class="fi-ta-content-ctn fp-ssl-hostname-table overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                                <table class="fi-ta-table w-full text-sm">
                                    <thead>
                                        <tr class="fi-ta-header-row">
                                            <th class="fi-ta-header-cell">Hostname</th>
                                            <th class="fi-ta-header-cell">Certificate</th>
                                            <th class="fi-ta-header-cell">Validity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sslHostnames as $row)
                                            <tr class="fi-ta-row">
                                                <td class="fi-ta-cell">{{ $row['hostname'] }}</td>
                                                <td class="fi-ta-cell">
                                                    <x-filament::badge :color="$row['has_certificate'] ? 'success' : 'warning'">
                                                        {{ $row['is_valid'] ? 'Active' : ($row['certificate_status'] !== '' ? ucfirst(str_replace('_', ' ', $row['certificate_status'])) : 'Pending') }}
                                                    </x-filament::badge>
                                                </td>
                                                <td class="fi-ta-cell">
                                                    <x-filament::badge :color="$row['is_valid'] ? 'success' : 'warning'">
                                                        {{ $row['is_valid'] ? 'Valid' : 'Pending' }}
                                                    </x-filament::badge>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-filament.app.settings.card>

                    @if ($this->isProMode())
                        <x-filament.app.settings.card
                            title="SSL Timeline"
                            description="Recent SSL and HTTPS-related actions recorded by FirePhage."
                            icon="heroicon-o-clock"
                        >
                            <x-filament.app.settings.key-value-grid :rows="[
                                ['label' => 'Most recent SSL action', 'value' => $this->lastAction(['acm.', 'site.control.https_enforced', 'site.control.tls1_enabled', 'site.control.tls1_1_enabled'])],
                                ['label' => 'Certificate state', 'value' => $certificateStatus],
                                ['label' => 'Legacy TLS posture', 'value' => collect([$this->tls10Enabled(), $this->tls11Enabled()])->filter()->isEmpty() ? 'Modern only' : 'Compatibility mode enabled'],
                            ]" />
                        </x-filament.app.settings.card>
                    @else
                        <x-filament::section heading="Need SSL History?" icon="heroicon-o-adjustments-horizontal">
                            <p class="text-sm">Switch to Pro mode for a fuller SSL activity timeline and deeper delivery history.</p>
                            <x-slot name="footer">
                                <x-filament::actions alignment="end">
                                    <x-filament::button wire:click="switchToProMode" color="gray">Switch to Pro mode</x-filament::button>
                                </x-filament::actions>
                            </x-slot>
                        </x-filament::section>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
