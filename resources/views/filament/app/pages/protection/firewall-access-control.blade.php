<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')

            <x-filament::section
                heading="Access Control"
                description="Configure country, continent, and IP access rules for the selected site."
                icon="heroicon-o-no-symbol"
            >
                <form wire:submit="createRules" class="space-y-4">
                    {{ $this->form }}

                    <x-filament::actions alignment="end">
                        <x-filament::button type="submit" icon="heroicon-m-shield-exclamation">
                            Save Rule Set
                        </x-filament::button>
                        <x-filament::button color="gray" wire:click="deployStagedRules" wire:loading.attr="disabled" wire:target="deployStagedRules">
                            Deploy staged rules
                        </x-filament::button>
                        <x-filament::button color="gray" wire:click="expireRulesNow" wire:loading.attr="disabled" wire:target="expireRulesNow">
                            Expire temporary rules
                        </x-filament::button>
                    </x-filament::actions>
                </form>
            </x-filament::section>

            <x-filament::section heading="How This Works" icon="heroicon-o-information-circle">
                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'Staging mode', 'value' => (bool) data_get($this->site->provider_meta, 'firewall_policy.staging_mode', false) ? 'Enabled' : 'Disabled'],
                    ['label' => 'Allowlist priority', 'value' => (bool) data_get($this->site->provider_meta, 'firewall_policy.allowlist_priority', true) ? 'Enabled' : 'Disabled'],
                    ['label' => 'Temporary blocks', 'value' => 'Supported via expiration date'],
                    ['label' => 'Bulk import', 'value' => 'Supported for IP and CIDR targets'],
                ]" />
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
