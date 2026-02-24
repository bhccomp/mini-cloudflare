<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament.app.settings.card
                title="Protection Control Stack"
                description="Operate SSL, CDN, cache, firewall, and origin controls for the selected site."
                icon="heroicon-o-shield-check"
                :status="str($this->site->status)->replace('_', ' ')->title()"
                :status-color="$this->badgeColor()"
            >
                <x-filament.app.settings.section
                    title="SSL / TLS"
                    description="Certificate and transport posture"
                    icon="heroicon-o-lock-closed"
                    :status="$this->certificateStatus()"
                    :status-color="$this->site->acm_certificate_arn ? 'success' : 'warning'"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Status', 'value' => $this->certificateStatus()],
                        ['label' => 'Health', 'value' => $this->distributionHealth()],
                        ['label' => 'Deployment', 'value' => $this->site->acm_certificate_arn ? 'Certificate requested' : 'Not started'],
                        ['label' => 'Last action', 'value' => $this->lastAction('acm.')],
                    ]" />

                    <x-slot name="actions">
                        <x-filament.app.settings.action-row>
                            <x-filament::button size="sm" wire:click="requestSsl">Request SSL</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="toggleHttpsEnforcement">HTTPS mode</x-filament::button>
                        </x-filament.app.settings.action-row>
                    </x-slot>
                </x-filament.app.settings.section>

                <x-filament.app.settings.section
                    title="CDN / Cache"
                    description="Edge delivery and cache controls"
                    icon="heroicon-o-globe-alt"
                    :status="$this->site->cloudfront_distribution_id ? 'Connected' : 'Not deployed'"
                    :status-color="$this->site->cloudfront_distribution_id ? 'success' : 'gray'"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Status', 'value' => $this->site->cloudfront_distribution_id ? 'Provisioned' : 'Not deployed'],
                        ['label' => 'Health', 'value' => $this->distributionHealth()],
                        ['label' => 'Deployment', 'value' => $this->site->cloudfront_domain_name ?: 'No edge domain yet'],
                        ['label' => 'Last action', 'value' => $this->lastAction('cloudfront.')],
                    ]" />

                    <x-slot name="actions">
                        <x-filament.app.settings.action-row>
                            <x-filament::button size="sm" color="gray" wire:click="purgeCache">Purge cache</x-filament::button>
                            <x-filament::button size="sm" wire:click="toggleCacheMode">Cache: {{ ucfirst($this->cacheMode()) }}</x-filament::button>
                        </x-filament.app.settings.action-row>
                    </x-slot>
                </x-filament.app.settings.section>

                <x-filament.app.settings.section
                    title="Firewall"
                    description="Threat filtering and emergency mode"
                    icon="heroicon-o-shield-check"
                    :status="$this->site->under_attack ? 'Under Attack Mode' : 'Baseline Protection'"
                    :status-color="$this->site->under_attack ? 'danger' : 'success'"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Status', 'value' => $this->site->waf_web_acl_arn ? 'Active' : 'Pending setup'],
                        ['label' => 'Health', 'value' => $this->site->under_attack ? 'Hardened' : 'Healthy'],
                        ['label' => 'Deployment', 'value' => $this->metricBlockedRequests() . ' blocked / 24h'],
                        ['label' => 'Last action', 'value' => $this->lastAction('waf.')],
                    ]" />

                    <x-slot name="actions">
                        <x-filament.app.settings.action-row>
                            <x-filament::button size="sm" color="danger" wire:click="toggleUnderAttack">
                                {{ $this->site->under_attack ? 'Disable Under Attack' : 'Enable Under Attack' }}
                            </x-filament::button>
                        </x-filament.app.settings.action-row>
                    </x-slot>
                </x-filament.app.settings.section>

                <x-filament.app.settings.section
                    title="Origin"
                    description="Origin endpoint and access posture"
                    icon="heroicon-o-server-stack"
                    status="Review access"
                    status-color="warning"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Status', 'value' => parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured'],
                        ['label' => 'Health', 'value' => 'Review direct access policy'],
                        ['label' => 'Deployment', 'value' => data_get($this->site->required_dns_records, 'control_panel.origin_lockdown', false) ? 'Origin lock-down enabled' : 'Origin lock-down pending'],
                        ['label' => 'Last action', 'value' => $this->lastAction('site.control.origin')],
                    ]" />

                    <x-slot name="actions">
                        <x-filament.app.settings.action-row>
                            <x-filament::button size="sm" wire:click="toggleOriginProtection">Origin protection</x-filament::button>
                        </x-filament.app.settings.action-row>
                    </x-slot>
                </x-filament.app.settings.section>
            </x-filament.app.settings.card>
        @endif
    </div>
</x-filament-panels::page>
