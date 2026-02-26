<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($status = $this->originExposureStatus())

    <div class="fp-protection-shell">
        @include('filament.app.pages.protection.technical-details')

        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section heading="Origin Security" description="Origin exposure and policy posture." icon="heroicon-o-server-stack">
                <x-slot name="footer">
                    <x-filament::actions alignment="end">
                        <x-filament::button wire:click="toggleOriginProtection" wire:loading.attr="disabled" wire:target="toggleOriginProtection">Apply Origin Policy</x-filament::button>
                    </x-filament::actions>
                </x-slot>

                <x-filament.app.settings.key-value-grid :rows="[
                    ['label' => 'Origin Exposure Status', 'value' => $status],
                    ['label' => 'Origin Host', 'value' => parse_url($this->site->origin_url, PHP_URL_HOST) ?: 'Not configured'],
                    ['label' => 'Origin Response Latency', 'value' => $this->originLatency()],
                    ['label' => 'Last Origin Policy Change', 'value' => $this->lastAction('site.control.origin')],
                    ['label' => 'Policy', 'value' => 'Managed by Edge Network'],
                ]" />
            </x-filament::section>

            <x-filament::section heading="What This Means" description="How origin exposure status is calculated." icon="heroicon-o-information-circle">
                <div class="grid gap-2 text-sm">
                    <p><strong>Protected:</strong> strict edge protection and origin controls are active.</p>
                    <p><strong>Partially Exposed:</strong> some controls are enabled, but direct origin access may still be possible.</p>
                    <p><strong>Exposed:</strong> origin can likely be reached directly without full edge controls.</p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
