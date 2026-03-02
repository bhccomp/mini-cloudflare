<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section
                heading="Create Rate Limit Rule"
                description="Define request ceilings and enforcement action for high-traffic paths."
                icon="heroicon-o-clock"
            >
                <form wire:submit="createRateLimit" class="space-y-4">
                    {{ $this->form }}

                    <x-filament::actions alignment="end">
                        <x-filament::button type="submit" icon="heroicon-m-plus">
                            Create rate limit
                        </x-filament::button>
                    </x-filament::actions>
                </form>
            </x-filament::section>

            <x-filament::section
                heading="Current Rules"
                description="Rules currently returned by edge security API."
                icon="heroicon-o-list-bullet"
            >
                <x-filament.app.settings.key-value-grid :rows="collect($this->rateLimits)->take(20)->map(function ($rule) {
                    $name = (string) (data_get($rule, 'name') ?: data_get($rule, 'Name') ?: data_get($rule, 'id') ?: 'Rule');
                    $value = 'Action '.(data_get($rule, 'actionType') ?? data_get($rule, 'ActionType') ?? '-').' | '
                        .'Enabled '.((data_get($rule, 'enabled') ?? data_get($rule, 'Enabled')) ? 'Yes' : 'No');

                    return ['label' => $name, 'value' => $value];
                })->all()" />
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

