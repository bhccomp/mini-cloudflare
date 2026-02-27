<div class="fi-topbar-item">
    <div class="inline-flex items-center rounded-lg border border-gray-200 p-1 dark:border-gray-700">
        <x-filament::button
            type="button"
            size="xs"
            :color="$mode === 'simple' ? 'primary' : 'gray'"
            wire:click="setMode('simple')"
            wire:loading.attr="disabled"
            wire:target="setMode"
        >
            Simple
        </x-filament::button>

        <x-filament::button
            type="button"
            size="xs"
            :color="$mode === 'pro' ? 'primary' : 'gray'"
            wire:click="setMode('pro')"
            wire:loading.attr="disabled"
            wire:target="setMode"
        >
            Pro
        </x-filament::button>
    </div>
</div>

