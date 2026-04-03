<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div
        class="fp-protection-shell space-y-6"
        x-data
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
        @if ($this->site && ! $this->isSiteLive() && $this->shouldPollStatus()) wire:poll.15s="pollStatus" @endif
    >
        <x-filament-actions::modals />

        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @if ($this->isSiteLive() && $this->isSimpleMode())
                <x-filament.app.simple-service-overview
                    :items="$this->simpleServiceOverview()"
                    :recommendation="$this->simpleServiceOverviewRecommendation()"
                    :show-pro-button="true"
                />
            @endif

            @if (
                ($this->isBunnyFlow() && $this->site->onboarding_status === \App\Models\Site::ONBOARDING_PROVISIONING_EDGE)
                || (! $this->isBunnyFlow() && $this->site->status === \App\Models\Site::STATUS_DEPLOYING)
            )
                <x-filament::section
                    heading="FirePhage Is Setting Up Protection"
                    description="This site is being provisioned now. Keep this page open while FirePhage prepares the edge and DNS target."
                    icon="heroicon-o-arrow-path"
                >
                    <div class="flex items-start gap-4 rounded-xl border border-primary-200 bg-primary-50 px-4 py-4 text-sm text-primary-950 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-100">
                        <svg class="fi-icon fi-size-lg mt-0.5 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 4V1m0 22v-3m8-8h3M1 12h3m13.657 5.657 2.121 2.121M4.222 4.222l2.121 2.121m11.314-2.121-2.121 2.121M6.343 17.657l-2.121 2.121" stroke="currentColor" stroke-linecap="round" stroke-width="1.75" opacity="0.35"/>
                            <path d="M12 4V1m5.657 5.343 2.121-2.121M20 12h3m-5.343 5.657 2.121 2.121" stroke="currentColor" stroke-linecap="round" stroke-width="1.75"/>
                        </svg>

                        <div class="space-y-2">
                            <p class="font-medium">Provisioning is in progress.</p>
                            <p>FirePhage will move you to the next DNS step as soon as the edge target is ready. This page refreshes automatically while setup is running.</p>
                        </div>
                    </div>
                </x-filament::section>
            @endif

            @if ($this->isSiteLive())
            @else
            @include('filament.app.pages.site-status-hub-onboarding')
            @endif

            @if ($this->isSiteLive())
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
            @endif
        @endif
    </div>
</x-filament-panels::page>
