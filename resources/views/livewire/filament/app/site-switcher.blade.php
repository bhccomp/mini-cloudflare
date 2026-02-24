<div class="fi-topbar-item" x-data="{ open: false }" @click.away="open = false">
    <button
        class="fi-topbar-item-btn"
        type="button"
        @click="open = ! open"
    >
        <div class="fi-topbar-item-label flex items-center gap-2">
            <span class="text-sm font-medium">{{ $this->selectedSite?->apex_domain ?? 'Select Site' }}</span>
            @if ($this->selectedSite)
                <x-filament::badge :color="$this->statusColor($this->selectedSite->status)">
                    {{ str($this->selectedSite->status)->replace('_', ' ')->title() }}
                </x-filament::badge>
            @endif
        </div>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition
        class="absolute z-50 mt-2 w-96 rounded-xl border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="space-y-1">
            @forelse ($sites as $site)
                <button
                    type="button"
                    wire:click="selectSite({{ $site->id }})"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    <span class="font-medium">{{ $site->apex_domain }}</span>
                    <x-filament::badge :color="$this->statusColor($site->status)">
                        {{ str($site->status)->replace('_', ' ')->title() }}
                    </x-filament::badge>
                </button>
            @empty
                <div class="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                    No sites yet. Add your first protected site.
                </div>
            @endforelse
        </div>

        <div class="mt-2 border-t border-gray-200 pt-2 dark:border-gray-800">
            <a
                href="{{ $this->addSiteUrl() }}"
                class="block rounded-lg px-3 py-2 text-sm font-medium text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-800"
            >
                + Add site
            </a>
            @if ($this->selectedSite)
                <button
                    type="button"
                    wire:click="clearSelectedSite"
                    class="mt-1 block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Clear selected site
                </button>
            @endif
        </div>
    </div>
</div>
