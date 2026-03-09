<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell space-y-6" @if ($this->site && ! $this->isSiteLive() && $this->shouldPollStatus()) wire:poll.15s="pollStatus" @endif>
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section
                heading="Troubleshooting Mode"
                description="Keep DNS on FirePhage/Bunny while disabling Bunny WAF and relaxing edge cache/optimizer behavior for testing."
                icon="heroicon-o-wrench-screwdriver"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->isTroubleshootingMode() ? 'warning' : 'success'">
                        {{ $this->isTroubleshootingMode() ? 'Enabled' : 'Disabled' }}
                    </x-filament::badge>
                </x-slot>

                <p class="text-sm">
                    Use this when testing whether edge filtering or caching is affecting an integration. Traffic still flows through Bunny; this is not a full DNS bypass.
                </p>

                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button
                            :color="$this->isTroubleshootingMode() ? 'warning' : 'gray'"
                            wire:click="toggleTroubleshootingMode"
                            wire:loading.attr="disabled"
                            wire:target="toggleTroubleshootingMode"
                        >
                            {{ $this->isTroubleshootingMode() ? 'Disable Troubleshooting Mode' : 'Enable Troubleshooting Mode' }}
                        </x-filament::button>
                    </x-filament::actions>
                </x-slot>
            </x-filament::section>

            @if ($this->isSiteLive())
            @if ($this->isSimpleMode())
                <x-filament::section heading="Need Deep Detail?" icon="heroicon-o-adjustments-horizontal">
                    <p>Simple mode keeps this page compact. Switch to Pro mode to include activity feed and deeper technical detail.</p>
                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button wire:click="switchToProMode" color="gray">Switch to Pro mode</x-filament::button>
                        </x-filament::actions>
                    </x-slot>
                </x-filament::section>
            @endif
            @else
            <x-filament::section
                heading="Site Setup Progress"
                description="Complete these onboarding steps to activate protection."
                icon="heroicon-o-queue-list"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$this->badgeColor()">
                        {{ $this->shouldShowEdgeRoutingWarning() ? $this->statusLabel() : ($this->isBunnyFlow() ? $this->onboardingLabel() : $this->statusLabel()) }}
                    </x-filament::badge>
                </x-slot>

                @foreach ($this->steps() as $index => $label)
                    <p>
                        <strong>Step {{ $index }}:</strong> {{ $label }}
                        @if ($this->currentStep() === $index)
                            <x-filament::badge color="primary">Current</x-filament::badge>
                        @endif
                    </p>
                @endforeach

                @if ($this->shouldPollStatus())
                    <p>Auto-refreshing status every 15 seconds.</p>
                @endif
            </x-filament::section>

            <x-filament::section heading="Next Best Action" icon="heroicon-o-forward">
                @if ($this->isBunnyFlow())
                    @if ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_DRAFT)
                        <p>Provision edge now to create DNS target records.</p>
                        <x-filament::button wire:click="requestSsl" wire:loading.attr="disabled" wire:target="requestSsl">Provision edge</x-filament::button>
                    @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_PROVISIONING_EDGE)
                        <p>Provisioning edge resources now. This can take a few minutes.</p>
                        <x-filament::button color="gray" disabled>Provisioning...</x-filament::button>
                    @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_PENDING_DNS_CUTOVER)
                        <p>Update DNS to the edge target below, then click <strong>Check now</strong>.</p>
                        <x-filament::button wire:click="checkCutover" wire:loading.attr="disabled" wire:target="checkCutover">Check now</x-filament::button>
                    @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING)
                        <p>DNS is verified. SSL certificate is still pending issuance.</p>
                        <x-filament::button wire:click="checkCutover" wire:loading.attr="disabled" wire:target="checkCutover">Check now</x-filament::button>
                    @else
                        <p>Provisioning failed. Review error and retry.</p>
                        <p><strong>Last error:</strong> {{ $this->site->last_error ?: 'No error message was recorded.' }}</p>
                        <x-filament::button color="warning" wire:click="requestSsl">Retry provisioning</x-filament::button>
                    @endif
                @else
                    @if ($this->site->status === \App\Models\Site::STATUS_DRAFT)
                        <p>Start provisioning to request a certificate and generate DNS validation records.</p>
                        <x-filament::button wire:click="requestSsl" wire:loading.attr="disabled" wire:target="requestSsl">Provision</x-filament::button>
                    @elseif ($this->site->status === \App\Models\Site::STATUS_PENDING_DNS_VALIDATION)
                        <p>Add the validation CNAME records below, then run a DNS validation check.</p>
                        <x-filament::button wire:click="checkDnsValidation" wire:loading.attr="disabled" wire:target="checkDnsValidation">Check DNS (validation)</x-filament::button>
                    @elseif ($this->site->status === \App\Models\Site::STATUS_DEPLOYING)
                        <p>Deploying edge resources now. This can take several minutes.</p>
                        <x-filament::button color="gray" disabled>Deploying...</x-filament::button>
                    @elseif ($this->site->status === \App\Models\Site::STATUS_READY_FOR_CUTOVER)
                        <p>Edge deployment is complete. Update traffic DNS to the edge target, then verify cutover.</p>
                        <x-filament::button wire:click="checkCutover" wire:loading.attr="disabled" wire:target="checkCutover">Check cutover</x-filament::button>
                    @else
                        <p>Provisioning failed. Review the error and retry.</p>
                        <p><strong>Last error:</strong> {{ $this->site->last_error ?: 'No error message was recorded.' }}</p>
                        <x-filament::actions>
                            <x-filament::button color="warning" wire:click="requestSsl">Retry provisioning</x-filament::button>
                            <x-filament::button color="gray" wire:click="checkDnsValidation">Retry DNS check</x-filament::button>
                        </x-filament::actions>
                    @endif
                @endif
            </x-filament::section>

            @if (! $this->isBunnyFlow() && $this->site->status === \App\Models\Site::STATUS_PENDING_DNS_VALIDATION)
                <x-filament::section heading="DNS Validation Records" icon="heroicon-o-server-stack">
                    <p>Add these DNS records exactly as shown, then click <strong>Check DNS (validation)</strong>.</p>

                    @foreach ($this->acmValidationRecords() as $record)
                        <x-filament::section compact secondary>
                            <p><strong>Type:</strong> {{ data_get($record, 'type', 'CNAME') }}</p>
                            <p><strong>Name:</strong> {{ data_get($record, 'name') }}</p>
                            <p><strong>Value:</strong> {{ data_get($record, 'value') }}</p>
                            <p><strong>TTL:</strong> {{ data_get($record, 'ttl', 'Auto') }}</p>
                            <x-slot name="footer">
                                <x-filament::actions>
                                    <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js((string) data_get($record, 'name')))">Copy name</x-filament::button>
                                    <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js((string) data_get($record, 'value')))">Copy value</x-filament::button>
                                </x-filament::actions>
                            </x-slot>
                        </x-filament::section>
                    @endforeach
                </x-filament::section>
            @endif

            @if ($this->isBunnyFlow() && in_array($this->site->onboarding_status, [\App\Models\Site::ONBOARDING_PENDING_DNS_CUTOVER, \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING], true))
                <x-filament::section heading="Traffic DNS Target" icon="heroicon-o-globe-alt">
                    <p>Point DNS to <strong>{{ $this->site->cloudfront_domain_name }}</strong>.</p>
                    <p>After updating DNS, click <strong>Check now</strong> or wait for auto-check.</p>

                    @foreach ($this->trafficDnsRecords() as $record)
                        <x-filament::section compact secondary>
                            <p><strong>Host:</strong> {{ data_get($record, 'name', data_get($record, 'host')) }}</p>
                            <p><strong>Type:</strong> {{ data_get($record, 'type', 'CNAME') }}</p>
                            <p><strong>Value:</strong> {{ data_get($record, 'value') }}</p>
                            <p><strong>TTL:</strong> {{ data_get($record, 'ttl', 'Auto') }}</p>
                            <p>{{ data_get($record, 'notes', data_get($record, 'note', '')) }}</p>
                            <x-slot name="footer">
                                <x-filament::actions>
                                    <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js((string) data_get($record, 'name', data_get($record, 'host'))))">Copy host</x-filament::button>
                                    <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js((string) data_get($record, 'value')))">Copy target</x-filament::button>
                                </x-filament::actions>
                            </x-slot>
                        </x-filament::section>
                    @endforeach
                </x-filament::section>
            @endif

            @if ($this->isBunnyFlow() && $this->site->onboarding_status === \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING)
                <x-filament::section heading="SSL Status" icon="heroicon-o-lock-closed">
                    <p>DNS cutover is verified. SSL certificate is still provisioning.</p>
                    <p>Keep this page open or click <strong>Check now</strong> until status becomes <strong>Live / Protected</strong>.</p>
                </x-filament::section>
            @endif

            @if (! $this->isBunnyFlow() && $this->site->status === \App\Models\Site::STATUS_READY_FOR_CUTOVER)
                <x-filament::section heading="Traffic DNS Target" icon="heroicon-o-globe-alt">
                    <p>Point traffic DNS to <strong>{{ $this->site->cloudfront_domain_name }}</strong>.</p>
                    <p>After updating DNS, click <strong>Check cutover</strong>.</p>

                    @foreach ($this->cutoverRecords() as $record)
                        <x-filament::section compact secondary>
                            <p><strong>Host:</strong> {{ $record['host'] }}</p>
                            <p><strong>Type:</strong> {{ $record['type'] }}</p>
                            <p><strong>Value:</strong> {{ $record['value'] }}</p>
                            <p><strong>TTL:</strong> {{ $record['ttl'] }}</p>
                            <p>{{ $record['note'] }}</p>
                            <x-slot name="footer">
                                <x-filament::actions>
                                    <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js($record['host']))">Copy host</x-filament::button>
                                    <x-filament::button color="gray" size="sm" x-on:click="navigator.clipboard.writeText(@js($record['value']))">Copy target</x-filament::button>
                                </x-filament::actions>
                            </x-slot>
                        </x-filament::section>
                    @endforeach
                </x-filament::section>
            @endif
            @endif
        @endif
    </div>
</x-filament-panels::page>
