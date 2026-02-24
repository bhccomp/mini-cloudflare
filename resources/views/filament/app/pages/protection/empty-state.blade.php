<x-filament::section icon="heroicon-o-globe-alt" :heading="$this->emptyStateHeading()" :description="$this->emptyStateDescription()">
    @once
        <style>
            .fp-site-list {
                display: grid;
                gap: 0.5rem;
            }

            .fp-site-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.45rem 0.65rem;
                border-radius: 0.55rem;
                border: 1px solid var(--gray-200);
                background: var(--gray-50);
            }

            .dark .fp-site-row {
                border-color: var(--gray-800);
                background: color-mix(in srgb, var(--gray-900) 90%, transparent);
            }
        </style>
    @endonce

    <div class="rounded-xl border border-dashed border-gray-300 p-4 dark:border-gray-700">
        <div class="flex flex-wrap gap-3">
            <x-filament::button tag="a" :href="\App\Filament\App\Resources\SiteResource::getUrl('create')">
                Add site
            </x-filament::button>
            <x-filament::button tag="a" :href="\App\Filament\App\Resources\SiteResource::getUrl('index')" color="gray">
                Go to Sites list
            </x-filament::button>
        </div>
    </div>

    @if ($this->hasAnySites && $this->availableSites->isNotEmpty())
        <div class="mt-4 grid gap-2">
            <p class="text-sm opacity-80">Select a site to continue:</p>

            <div class="fp-site-list">
                @foreach ($this->availableSites as $site)
                    <div class="fp-site-row text-sm">
                        <x-filament::link :href="$this->currentPageBaseUrl() . '?site_id=' . $site->id" class="font-medium">
                            {{ $site->apex_domain }}
                        </x-filament::link>

                        <x-filament::badge :color="$this->badgeColor($site->status)" size="sm">
                            {{ $this->statusLabel($site->status) }}
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament::section>
