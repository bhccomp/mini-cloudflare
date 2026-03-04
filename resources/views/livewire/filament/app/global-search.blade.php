<x-filament::dropdown placement="bottom-end" width="xl" :teleport="true" class="fi-topbar-item">
    <x-slot name="trigger">
        <button class="fi-topbar-item-btn" type="button" aria-label="Global search">
            <div class="fi-topbar-item-label flex items-center gap-2">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-5 w-5" />
                <span class="hidden text-sm md:inline">Search</span>
            </div>
        </button>
    </x-slot>

    <x-filament::dropdown.list>
        <div x-data x-id="['global-search']">
            <label x-bind:for="$id('global-search')" class="fi-sr-only">Global search</label>
            <x-filament::input
                x-bind:id="$id('global-search')"
                wire:model.live.debounce.250ms="query"
                type="search"
                placeholder="Search pages, sites, alerts..."
                autofocus
            />
        </div>
    </x-filament::dropdown.list>

    <x-filament::dropdown.list class="max-h-96 overflow-y-auto">
        @forelse ($results as $item)
            <x-filament::dropdown.list.item
                tag="button"
                type="button"
                wire:click="open('{{ $item['url'] }}')"
            >
                <div class="flex w-full items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ $item['label'] }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['meta'] }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-filament::badge :color="$this->badgeColor($item['type'])">
                            {{ $item['type'] }}
                        </x-filament::badge>
                        @if (($item['type'] ?? null) === 'Site' && isset($item['status']))
                            <x-filament::badge :color="$this->statusColor($item['status'])">
                                {{ $this->shortStatusLabel($item['status']) }}
                            </x-filament::badge>
                        @endif
                    </div>
                </div>
            </x-filament::dropdown.list.item>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                No results found.
            </div>
        @endforelse
    </x-filament::dropdown.list>
</x-filament::dropdown>
