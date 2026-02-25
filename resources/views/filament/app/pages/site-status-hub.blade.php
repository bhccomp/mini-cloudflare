<x-filament-panels::page>
    <style>
        .fp-status-shell {
            width: 100%;
            max-width: 72rem;
            margin-inline: auto;
            display: grid;
            gap: 1rem;
        }

        .fp-stepper {
            display: grid;
            gap: 0.75rem;
        }

        .fp-step {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            display: grid;
            gap: 0.4rem;
        }

        .dark .fp-step {
            border-color: var(--gray-800);
            background: color-mix(in srgb, var(--gray-900) 90%, transparent);
        }

        .fp-step.is-active {
            border-color: color-mix(in srgb, var(--primary-600) 50%, var(--gray-200));
            box-shadow: 0 0 0 1px color-mix(in srgb, var(--primary-600) 35%, transparent) inset;
        }

        .fp-step-index {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .fp-dns-grid {
            display: grid;
            gap: 0.75rem;
        }

        .fp-dns-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.75rem;
            align-items: center;
        }

        .fp-dns-copy-group {
            display: inline-flex;
            gap: 0.5rem;
        }

        .fp-loading-inline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            background: var(--gray-50);
        }

        .dark .fp-loading-inline {
            border-color: var(--gray-800);
            background: color-mix(in srgb, var(--gray-900) 90%, transparent);
        }

        @media (max-width: 1024px) {
            .fp-dns-row {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        window.fpCopyText = async function (text) {
            const value = String(text ?? '');

            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(value);
                    return true;
                }
            } catch (e) {
                // Continue with fallback.
            }

            const area = document.createElement('textarea');
            area.value = value;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.focus();
            area.select();
            const copied = document.execCommand('copy');
            document.body.removeChild(area);

            return copied;
        };
    </script>

    <div
        class="fp-status-shell"
        @if ($this->site && $this->shouldPollStatus())
            wire:poll.15s="pollStatus"
        @endif
    >
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section
                heading="Site Status Hub"
                description="{{ $this->isBunnyFlow() ? 'Bunny-first onboarding: provision edge, update DNS, then wait for SSL activation.' : 'Follow this setup flow in order. Traffic should not be pointed to CloudFront until deployment is complete.' }}"
                icon="heroicon-o-queue-list"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->badgeColor()">
                        {{ $this->isBunnyFlow() ? $this->onboardingLabel() : $this->statusLabel() }}
                    </x-filament::badge>
                </x-slot>

                <div class="fp-stepper" style="grid-template-columns: repeat({{ count($this->steps()) }}, minmax(0, 1fr));">
                    @foreach ($this->steps() as $index => $label)
                        <div class="fp-step {{ $this->currentStep() === $index ? 'is-active' : '' }}">
                            <p class="fp-step-index">Step {{ $index }}</p>
                            <p>{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($this->shouldPollStatus())
                    <p style="margin-top: 0.75rem; opacity: 0.7;">Auto-refreshing status every 15 seconds.</p>
                @endif
            </x-filament::section>

            <x-filament::section heading="Next Best Action" icon="heroicon-o-forward">
                @if ($this->isBunnyFlow())
                    @if ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_DRAFT)
                        <p>Provision Bunny edge now to create your Pull Zone and DNS target records.</p>
                        <x-filament::button wire:click="requestSsl" wire:loading.attr="disabled" wire:target="requestSsl">Provision edge</x-filament::button>
                    @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_PROVISIONING_EDGE)
                        <p>Provisioning edge resources now. This can take a few minutes.</p>
                        <x-filament::button color="gray" disabled>Provisioning...</x-filament::button>
                    @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_PENDING_DNS_CUTOVER)
                        <p>Update DNS to the Bunny edge target below, then click <strong>Check now</strong>.</p>
                        <x-filament::button wire:click="checkCutover" wire:loading.attr="disabled" wire:target="checkCutover">Check now</x-filament::button>
                    @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING)
                        <p>DNS is verified. SSL certificate is still pending issuance.</p>
                        <x-filament::button wire:click="checkCutover" wire:loading.attr="disabled" wire:target="checkCutover">Check now</x-filament::button>
                    @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_LIVE)
                        <p>Protection is active.</p>
                        <div style="display: inline-flex; gap: 0.5rem; flex-wrap: wrap;">
                            <x-filament::button tag="a" :href="\App\Filament\App\Pages\Dashboard::getUrl(['site_id' => $this->site->id])">Open overview</x-filament::button>
                            <x-filament::button color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                        </div>
                    @else
                        <p>Provisioning failed. Review error and retry.</p>
                        <p><strong>Last error:</strong> {{ $this->site->last_error ?: 'No error message was recorded.' }}</p>
                        <x-filament::button color="warning" wire:click="requestSsl">Retry provisioning</x-filament::button>
                    @endif
                @else
                    @if ($this->site->status === \App\Models\Site::STATUS_DRAFT)
                        <p>Start provisioning to request an ACM certificate and generate DNS validation records.</p>
                        <x-filament::button wire:click="requestSsl" wire:loading.attr="disabled" wire:target="requestSsl">Provision</x-filament::button>
                    @elseif ($this->site->status === \App\Models\Site::STATUS_PENDING_DNS_VALIDATION)
                        <p>Add the validation CNAME records below, then run a DNS validation check.</p>
                        <x-filament::button wire:click="checkDnsValidation" wire:loading.attr="disabled" wire:target="checkDnsValidation">Check DNS (validation)</x-filament::button>
                    @elseif ($this->site->status === \App\Models\Site::STATUS_DEPLOYING)
                        <p>Deploying edge resources now. This can take several minutes.</p>
                        <x-filament::button color="gray" disabled>Deploying...</x-filament::button>
                    @elseif ($this->site->status === \App\Models\Site::STATUS_READY_FOR_CUTOVER)
                        <p>Edge deployment is complete. Update traffic DNS to CloudFront, then verify cutover.</p>
                        <x-filament::button wire:click="checkCutover" wire:loading.attr="disabled" wire:target="checkCutover">Check cutover</x-filament::button>
                    @elseif ($this->site->status === \App\Models\Site::STATUS_ACTIVE)
                        <p>Protection is active. You can continue with cache, WAF, and SSL operations.</p>
                        <div style="display: inline-flex; gap: 0.5rem; flex-wrap: wrap;">
                            <x-filament::button tag="a" :href="\App\Filament\App\Pages\Dashboard::getUrl(['site_id' => $this->site->id])">Open overview</x-filament::button>
                            <x-filament::button color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                        </div>
                    @else
                        <p>Provisioning failed. Review the error and retry.</p>
                        <p><strong>Last error:</strong> {{ $this->site->last_error ?: 'No error message was recorded.' }}</p>
                        <div style="display: inline-flex; gap: 0.5rem; flex-wrap: wrap;">
                            <x-filament::button color="warning" wire:click="requestSsl">Retry provisioning</x-filament::button>
                            <x-filament::button color="gray" wire:click="checkDnsValidation">Retry DNS check</x-filament::button>
                        </div>
                    @endif
                @endif

                <div class="fp-loading-inline" wire:loading.delay wire:target="requestSsl,checkDnsValidation,checkCutover">
                    <x-filament::loading-indicator class="h-4 w-4" />
                    <span>Working on it. This page will auto-refresh as state changes.</span>
                </div>
            </x-filament::section>

            @if (! $this->isBunnyFlow() && $this->site->status === \App\Models\Site::STATUS_PENDING_DNS_VALIDATION)
                <x-filament::section heading="DNS Validation Records" icon="heroicon-o-server-stack">
                    <p>Add these DNS records exactly as shown, then click <strong>Check DNS (validation)</strong>.</p>

                    <div class="fp-dns-grid">
                        @foreach ($this->acmValidationRecords() as $record)
                            <x-filament::section compact secondary>
                                <div class="fp-dns-row" x-data="{ copied: '' }">
                                    <div>
                                        <p><strong>Type:</strong> {{ data_get($record, 'type', 'CNAME') }}</p>
                                        <p><strong>Name:</strong> {{ data_get($record, 'name') }}</p>
                                        <p><strong>Value:</strong> {{ data_get($record, 'value') }}</p>
                                        <p><strong>TTL:</strong> {{ data_get($record, 'ttl', 'Auto') }}</p>
                                    </div>
                                    <div class="fp-dns-copy-group">
                                        <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js((string) data_get($record, 'name'))); copied = 'Name copied'">Copy name</x-filament::button>
                                        <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js((string) data_get($record, 'value'))); copied = 'Value copied'">Copy value</x-filament::button>
                                        <span x-text="copied"></span>
                                    </div>
                                </div>
                            </x-filament::section>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @if ($this->isBunnyFlow() && in_array($this->site->onboarding_status, [\App\Models\Site::ONBOARDING_PENDING_DNS_CUTOVER, \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING], true))
                <x-filament::section heading="Traffic DNS Target" icon="heroicon-o-globe-alt">
                    <p>Point DNS to <strong>{{ $this->site->cloudfront_domain_name }}</strong>.</p>
                    <p>After updating DNS, click <strong>Check now</strong> or wait for auto-check.</p>

                    <div class="fp-dns-grid">
                        @foreach ($this->trafficDnsRecords() as $record)
                            <x-filament::section compact secondary>
                                <div class="fp-dns-row" x-data="{ copied: '' }">
                                    <div>
                                        <p><strong>Host:</strong> {{ data_get($record, 'name', data_get($record, 'host')) }}</p>
                                        <p><strong>Type:</strong> {{ data_get($record, 'type', 'CNAME') }}</p>
                                        <p><strong>Value:</strong> {{ data_get($record, 'value') }}</p>
                                        <p><strong>TTL:</strong> {{ data_get($record, 'ttl', 'Auto') }}</p>
                                        <p>{{ data_get($record, 'notes', data_get($record, 'note', '')) }}</p>
                                    </div>
                                    <div class="fp-dns-copy-group">
                                        <x-filament::button color="gray" size="sm" x-on:click="await window.fpCopyText(@js((string) data_get($record, 'name', data_get($record, 'host')))); copied = 'Host copied'">Copy host</x-filament::button>
                                        <x-filament::button color="gray" size="sm" x-on:click="await window.fpCopyText(@js((string) data_get($record, 'value'))); copied = 'Target copied'">Copy target</x-filament::button>
                                        <span x-text="copied"></span>
                                    </div>
                                </div>
                            </x-filament::section>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @if ($this->isBunnyFlow() && $this->site->onboarding_status === \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING)
                <x-filament::section heading="SSL Status" icon="heroicon-o-lock-closed">
                    <p>DNS cutover is verified. Bunny SSL certificate is still provisioning.</p>
                    <p>Keep this page open or click <strong>Check now</strong> until status becomes <strong>Live / Protected</strong>.</p>
                </x-filament::section>
            @endif

            @if (! $this->isBunnyFlow() && $this->site->status === \App\Models\Site::STATUS_READY_FOR_CUTOVER)
                <x-filament::section heading="Traffic DNS Target" icon="heroicon-o-globe-alt">
                    <p>Point traffic DNS to <strong>{{ $this->site->cloudfront_domain_name }}</strong>.</p>
                    <p>After updating DNS, click <strong>Check cutover</strong>.</p>

                    <div class="fp-dns-grid">
                        @foreach ($this->cutoverRecords() as $record)
                            <x-filament::section compact secondary>
                                <div class="fp-dns-row" x-data="{ copied: '' }">
                                    <div>
                                        <p><strong>Host:</strong> {{ $record['host'] }}</p>
                                        <p><strong>Type:</strong> {{ $record['type'] }}</p>
                                        <p><strong>Value:</strong> {{ $record['value'] }}</p>
                                        <p><strong>TTL:</strong> {{ $record['ttl'] }}</p>
                                        <p>{{ $record['note'] }}</p>
                                    </div>
                                    <div class="fp-dns-copy-group">
                                        <x-filament::button color="gray" size="sm" x-on:click="await window.fpCopyText(@js($record['host'])); copied = 'Host copied'">Copy host</x-filament::button>
                                        <x-filament::button color="gray" size="sm" x-on:click="await window.fpCopyText(@js($record['value'])); copied = 'Target copied'">Copy target</x-filament::button>
                                        <span x-text="copied"></span>
                                    </div>
                                </div>
                            </x-filament::section>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
