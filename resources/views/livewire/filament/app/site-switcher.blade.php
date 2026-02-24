<div class="fi-topbar-item relative" x-data="{ open: false }" @click.away="open = false">
    <button class="fi-topbar-item-btn" type="button" @click="open = ! open">
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

    <div x-cloak x-show="open" x-transition class="absolute z-50 mt-2 w-[34rem] rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-3 flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
            <button
                type="button"
                wire:click="selectSite('all')"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800"
            >
                <span class="font-medium">All sites</span>
                @if (! $this->selectedSiteId)
                    <x-filament::badge color="gray">Selected</x-filament::badge>
                @endif
            </button>
            <a href="{{ $this->addSiteUrl() }}" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-800">
                + Add site
            </a>
        </div>

        <div class="mb-3">
            <x-filament::input.wrapper>
                <x-filament::input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Search sites..."
                />
            </x-filament::input.wrapper>
        </div>

        <div class="max-h-80 space-y-1 overflow-y-auto">
            @forelse ($sites as $site)
                <button
                    type="button"
                    wire:click="selectSite('{{ $site->id }}')"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    <span class="font-medium">{{ $site->apex_domain }}</span>
                    <span class="flex items-center gap-2">
                        <x-filament::badge :color="$this->statusColor($site->status)">
                            {{ $this->shortStatusLabel($site->status) }}
                        </x-filament::badge>
                        @if ((int) $this->selectedSiteId === (int) $site->id)
                            <x-filament::badge color="gray">Selected</x-filament::badge>
                        @endif
                    </span>
                </button>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                    No sites yet.
                </div>
            @endforelse
        </div>
    </div>
</div>
