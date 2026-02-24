<x-filament::dropdown placement="bottom-start" width="xl" :teleport="true" class="fi-topbar-item">
    <x-slot name="trigger">
        <button class="fi-topbar-item-btn" type="button">
            <div class="fi-topbar-item-label flex items-center gap-2">
                <span class="text-sm font-medium">{{ $this->selectedLabel }}</span>
                @if ($this->selectedSiteId)
                    @php($selected = $sites->firstWhere('id', $this->selectedSiteId))
                    @if ($selected)
                        <x-filament::badge :color="$this->statusColor($selected->status)">
                            {{ $this->shortStatusLabel($selected->status) }}
                        </x-filament::badge>
                    @endif
                @endif
            </div>
        </button>
    </x-slot>

    <x-filament::dropdown.list class="w-[36rem]">
        <div class="flex items-center justify-between gap-2">
            <x-filament::dropdown.list.item
                icon="heroicon-m-squares-2x2"
                tag="button"
                type="button"
                wire:click="selectSite('all')"
            >
                All sites
            </x-filament::dropdown.list.item>

            <x-filament::dropdown.list.item
                icon="heroicon-m-plus"
                :href="$this->addSiteUrl()"
                tag="a"
            >
                Add site
            </x-filament::dropdown.list.item>
        </div>
    </x-filament::dropdown.list>

    <x-filament::dropdown.list>
        <div x-data x-id="['site-search']">
            <label x-bind:for="$id('site-search')" class="fi-sr-only">Search sites</label>
            <x-filament::input
                x-bind:id="$id('site-search')"
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Search sites..."
            />
        </div>
    </x-filament::dropdown.list>

    <x-filament::dropdown.list class="max-h-96 overflow-y-auto">
        @forelse ($sites as $site)
            <x-filament::dropdown.list.item
                tag="button"
                type="button"
                wire:click="selectSite('{{ $site->id }}')"
            >
                <div class="flex w-full items-center justify-between gap-3">
                    <span class="font-medium">{{ $site->apex_domain }}</span>
                    <span class="flex items-center gap-2">
                        <x-filament::badge :color="$this->statusColor($site->status)">
                            {{ $this->shortStatusLabel($site->status) }}
                        </x-filament::badge>
                        @if ((int) $this->selectedSiteId === (int) $site->id)
                            <x-filament::badge color="gray">Selected</x-filament::badge>
                        @endif
                    </span>
                </div>
            </x-filament::dropdown.list.item>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                No sites yet.
            </div>
        @endforelse
    </x-filament::dropdown.list>
</x-filament::dropdown>
