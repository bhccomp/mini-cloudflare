<x-filament::section icon="heroicon-o-globe-alt" :heading="$this->emptyStateHeading()" :description="$this->emptyStateDescription()">
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
</x-filament::section>
