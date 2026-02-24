<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="rounded-2xl bg-gradient-to-r from-sky-500/20 via-sky-500/10 to-transparent p-6 ring-1 ring-sky-500/20 dark:from-sky-500/15 dark:via-sky-500/5">
                <p class="text-xs uppercase tracking-wider text-gray-500">CDN</p>
                <h2 class="mt-1 text-2xl font-semibold">{{ $this->site->apex_domain }}</h2>
            </div>

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
