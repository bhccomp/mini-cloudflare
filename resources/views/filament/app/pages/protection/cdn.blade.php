<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <x-filament::section icon="heroicon-o-globe-alt" heading="CDN" description="Distribution and edge delivery controls.">
                <div class="grid gap-4 md:grid-cols-4">
                    <div><p class="text-sm text-gray-500">Status</p><p class="font-medium">{{ $this->site->cloudfront_distribution_id ? 'Provisioned' : 'Not deployed' }}</p></div>
                    <div><p class="text-sm text-gray-500">Health</p><p class="font-medium">{{ $this->distributionHealth() }}</p></div>
                    <div><p class="text-sm text-gray-500">Last deployment action</p><p class="font-medium">{{ $this->lastAction('cloudfront.') }}</p></div>
                    <div class="flex items-end"><x-filament::button size="sm" color="gray" wire:click="purgeCache">Purge cache</x-filament::button></div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
