<x-filament::section
    :aside="true"
    :heading="__('filament-breezy::default.profile.personal_info.heading')"
    :description="__('filament-breezy::default.profile.personal_info.subheading')"
>
    <form wire:submit.prevent="submit" style="display: grid; row-gap: 2rem;">
        {{ $this->form }}

        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
            <x-filament::button type="submit" form="submit">
                {{ __('filament-breezy::default.profile.personal_info.submit.label') }}
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
