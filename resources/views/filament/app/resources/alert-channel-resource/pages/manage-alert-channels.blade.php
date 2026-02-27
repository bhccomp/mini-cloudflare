<x-filament-panels::page>
    <x-filament::section
        heading="Alert Delivery Settings"
        description="Configure where security alerts are sent for your organization."
        icon="heroicon-o-bell-alert"
    >
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-m-check">
                    Save settings
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
