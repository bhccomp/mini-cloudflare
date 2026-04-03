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

@if (! $this->isBunnyFlow() && $this->site->status === \App\Models\Site::STATUS_PENDING_DNS_VALIDATION)
    <x-filament::section heading="DNS Validation Records" icon="heroicon-o-server-stack">
        <div class="space-y-3 text-sm">
            <p>Add these DNS records exactly as shown at your DNS provider.</p>
            <ol class="list-decimal space-y-1 pl-5">
                <li>Open your DNS provider for this domain.</li>
                <li>Add or update the validation records below.</li>
                <li>Return here and click <strong>Check DNS (validation)</strong>.</li>
            </ol>
        </div>

        <div class="pt-4 space-y-4">
            @foreach ($this->acmValidationRecords() as $record)
                <x-filament::section compact secondary>
                    <p><strong>Type:</strong> {{ data_get($record, 'type', 'CNAME') }}</p>
                    <p><strong>Name:</strong> {{ data_get($record, 'name') }}</p>
                    <p><strong>Value:</strong> {{ data_get($record, 'value') }}</p>
                    <p><strong>TTL:</strong> {{ data_get($record, 'ttl', 'Auto') }}</p>
                    <x-slot name="footer">
                        <x-filament::actions>
                            <div
                                x-data="{ copied: false, timer: null, key: 'copy-name-{{ md5((string) data_get($record, 'name')) }}' }"
                                x-on:firephage-copy-success.window="
                                    if ($event.detail.key !== key) return;
                                    copied = true;
                                    if (timer) clearTimeout(timer);
                                    timer = setTimeout(() => copied = false, 2000);
                                "
                            >
                                <x-filament::button type="button" color="gray" size="sm" wire:click="copyToClipboard('{{ base64_encode((string) data_get($record, 'name')) }}', 'Name', 'copy-name-{{ md5((string) data_get($record, 'name')) }}')">
                                    <span x-show="! copied">Copy name</span>
                                    <span x-show="copied" x-cloak>Copied</span>
                                </x-filament::button>
                            </div>
                            <div
                                x-data="{ copied: false, timer: null, key: 'copy-value-{{ md5((string) data_get($record, 'value')) }}' }"
                                x-on:firephage-copy-success.window="
                                    if ($event.detail.key !== key) return;
                                    copied = true;
                                    if (timer) clearTimeout(timer);
                                    timer = setTimeout(() => copied = false, 2000);
                                "
                            >
                                <x-filament::button type="button" color="gray" size="sm" wire:click="copyToClipboard('{{ base64_encode((string) data_get($record, 'value')) }}', 'Value', 'copy-value-{{ md5((string) data_get($record, 'value')) }}')">
                                    <span x-show="! copied">Copy value</span>
                                    <span x-show="copied" x-cloak>Copied</span>
                                </x-filament::button>
                            </div>
                        </x-filament::actions>
                    </x-slot>
                </x-filament::section>
            @endforeach
        </div>

        <div class="mt-6 space-y-4 border-t border-gray-200 pt-4 text-sm dark:border-white/10">
            <p class="font-semibold">If you would rather not handle DNS changes yourself, open a support ticket and FirePhage can handle the onboarding for you at no extra cost.</p>

            <div>
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="mountAction('requestDnsAssistance')"
                >
                    Ask FirePhage To Handle DNS
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
@endif

@if ($this->isBunnyFlow() && in_array($this->site->onboarding_status, [\App\Models\Site::ONBOARDING_PENDING_DNS_CUTOVER, \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING], true))
    <x-filament::section heading="Traffic DNS Target" icon="heroicon-o-globe-alt">
        <div class="space-y-3 text-sm">
            <p>Point DNS to <strong>{{ $this->site->cloudfront_domain_name }}</strong>.</p>
            <ol class="list-decimal space-y-1 pl-5">
                <li>Open your DNS provider for this domain.</li>
                <li>Update the records below so traffic points to FirePhage.</li>
                <li>Return here and click <strong>Check now</strong>, or wait for automatic verification.</li>
            </ol>
        </div>

        <div class="pt-4 space-y-4">
            @foreach ($this->trafficDnsRecords() as $record)
                <x-filament::section compact secondary>
                    <p><strong>Host:</strong> {{ data_get($record, 'name', data_get($record, 'host')) }}</p>
                    <p><strong>Type:</strong> {{ data_get($record, 'type', 'CNAME') }}</p>
                    <p><strong>Value:</strong> {{ data_get($record, 'value') }}</p>
                    <p><strong>TTL:</strong> {{ data_get($record, 'ttl', 'Auto') }}</p>
                    <p>{{ data_get($record, 'notes', data_get($record, 'note', '')) }}</p>
                    <x-slot name="footer">
                        <x-filament::actions>
                            <div
                                x-data="{ copied: false, timer: null, key: 'copy-host-{{ md5((string) data_get($record, 'name', data_get($record, 'host'))) }}' }"
                                x-on:firephage-copy-success.window="
                                    if ($event.detail.key !== key) return;
                                    copied = true;
                                    if (timer) clearTimeout(timer);
                                    timer = setTimeout(() => copied = false, 2000);
                                "
                            >
                                <x-filament::button type="button" color="gray" size="sm" wire:click="copyToClipboard('{{ base64_encode((string) data_get($record, 'name', data_get($record, 'host'))) }}', 'Host', 'copy-host-{{ md5((string) data_get($record, 'name', data_get($record, 'host'))) }}')">
                                    <span x-show="! copied">Copy host</span>
                                    <span x-show="copied" x-cloak>Copied</span>
                                </x-filament::button>
                            </div>
                            <div
                                x-data="{ copied: false, timer: null, key: 'copy-target-{{ md5((string) data_get($record, 'value')) }}-{{ $loop->index }}' }"
                                x-on:firephage-copy-success.window="
                                    if ($event.detail.key !== key) return;
                                    copied = true;
                                    if (timer) clearTimeout(timer);
                                    timer = setTimeout(() => copied = false, 2000);
                                "
                            >
                                <x-filament::button type="button" color="gray" size="sm" wire:click="copyToClipboard('{{ base64_encode((string) data_get($record, 'value')) }}', 'Target', 'copy-target-{{ md5((string) data_get($record, 'value')) }}-{{ $loop->index }}')">
                                    <span x-show="! copied">Copy target</span>
                                    <span x-show="copied" x-cloak>Copied</span>
                                </x-filament::button>
                            </div>
                        </x-filament::actions>
                    </x-slot>
                </x-filament::section>
            @endforeach
        </div>

        <div class="mt-6 space-y-4 border-t border-gray-200 pt-4 text-sm dark:border-white/10">
            <p class="font-semibold">If you would rather not handle DNS changes yourself, open a support ticket and FirePhage can handle the cutover for you at no extra cost.</p>

            <div>
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="mountAction('requestDnsAssistance')"
                >
                    Ask FirePhage To Handle DNS
                </x-filament::button>
            </div>
        </div>
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
        <div class="space-y-3 text-sm">
            <p>Point traffic DNS to <strong>{{ $this->site->cloudfront_domain_name }}</strong>.</p>
            <ol class="list-decimal space-y-1 pl-5">
                <li>Open your DNS provider for this domain.</li>
                <li>Update the traffic records below so visitors route through FirePhage.</li>
                <li>Return here and click <strong>Check cutover</strong>.</li>
            </ol>
        </div>

        <div class="pt-4 space-y-4">
            @foreach ($this->cutoverRecords() as $record)
                <x-filament::section compact secondary>
                    <p><strong>Host:</strong> {{ $record['host'] }}</p>
                    <p><strong>Type:</strong> {{ $record['type'] }}</p>
                    <p><strong>Value:</strong> {{ $record['value'] }}</p>
                    <p><strong>TTL:</strong> {{ $record['ttl'] }}</p>
                    <p>{{ $record['note'] }}</p>
                    <x-slot name="footer">
                        <x-filament::actions>
                            <div
                                x-data="{ copied: false, timer: null, key: 'copy-host-{{ md5((string) $record['host']) }}' }"
                                x-on:firephage-copy-success.window="
                                    if ($event.detail.key !== key) return;
                                    copied = true;
                                    if (timer) clearTimeout(timer);
                                    timer = setTimeout(() => copied = false, 2000);
                                "
                            >
                                <x-filament::button type="button" color="gray" size="sm" wire:click="copyToClipboard('{{ base64_encode((string) $record['host']) }}', 'Host', 'copy-host-{{ md5((string) $record['host']) }}')">
                                    <span x-show="! copied">Copy host</span>
                                    <span x-show="copied" x-cloak>Copied</span>
                                </x-filament::button>
                            </div>
                            <div
                                x-data="{ copied: false, timer: null, key: 'copy-target-{{ md5((string) $record['value']) }}-{{ $loop->index }}' }"
                                x-on:firephage-copy-success.window="
                                    if ($event.detail.key !== key) return;
                                    copied = true;
                                    if (timer) clearTimeout(timer);
                                    timer = setTimeout(() => copied = false, 2000);
                                "
                            >
                                <x-filament::button type="button" color="gray" size="sm" wire:click="copyToClipboard('{{ base64_encode((string) $record['value']) }}', 'Target', 'copy-target-{{ md5((string) $record['value']) }}-{{ $loop->index }}')">
                                    <span x-show="! copied">Copy target</span>
                                    <span x-show="copied" x-cloak>Copied</span>
                                </x-filament::button>
                            </div>
                        </x-filament::actions>
                    </x-slot>
                </x-filament::section>
            @endforeach
        </div>

        <div class="mt-6 space-y-4 border-t border-gray-200 pt-4 text-sm dark:border-white/10">
            <p class="font-semibold">If you would rather not handle DNS changes yourself, open a support ticket and FirePhage can handle the cutover for you at no extra cost.</p>

            <div>
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="mountAction('requestDnsAssistance')"
                >
                    Ask FirePhage To Handle DNS
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
@endif

<x-filament::section heading="Next Best Action" icon="heroicon-o-forward">
    @if ($this->isBunnyFlow())
        @if ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_DRAFT)
            <p>Provision edge now to create DNS target records.</p>
            <x-filament::button wire:click="requestSsl" wire:loading.attr="disabled" wire:target="requestSsl">Provision edge</x-filament::button>
        @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_PROVISIONING_EDGE)
            <p>Provisioning edge resources now. This can take a few minutes.</p>
            <x-filament::button color="gray" disabled>Provisioning...</x-filament::button>
        @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_PENDING_DNS_CUTOVER)
            <p>After you update the DNS records above, click below to verify the cutover.</p>
            <x-filament::button wire:click="checkCutover" wire:loading.attr="disabled" wire:target="checkCutover">Check now</x-filament::button>
        @elseif ($this->site->onboarding_status === \App\Models\Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING)
            <p>DNS is verified. FirePhage is waiting for SSL to finish issuing.</p>
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
            <p>After you add the DNS validation records above, verify them here.</p>
            <x-filament::button wire:click="checkDnsValidation" wire:loading.attr="disabled" wire:target="checkDnsValidation">Check DNS (validation)</x-filament::button>
        @elseif ($this->site->status === \App\Models\Site::STATUS_DEPLOYING)
            <p>Deploying edge resources now. This can take several minutes.</p>
            <x-filament::button color="gray" disabled>Deploying...</x-filament::button>
        @elseif ($this->site->status === \App\Models\Site::STATUS_READY_FOR_CUTOVER)
            <p>After you update the traffic DNS records above, verify cutover here.</p>
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
