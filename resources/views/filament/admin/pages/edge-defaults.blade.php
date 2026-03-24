<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <x-filament::actions alignment="end">
                <x-filament::button color="gray" type="button" wire:click="applyToExistingSites" wire:loading.attr="disabled" wire:target="applyToExistingSites">
                    Apply hidden security defaults to existing Bunny sites
                </x-filament::button>

                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
                    Save defaults
                </x-filament::button>
            </x-filament::actions>
        </form>
    </div>
</x-filament-panels::page>
