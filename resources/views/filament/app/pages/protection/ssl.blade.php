<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.site-context-header')

            <x-filament::section icon="heroicon-o-lock-closed" heading="SSL/TLS" description="Certificate lifecycle and encrypted transport settings.">
                <div class="grid gap-4 md:grid-cols-4">
                    <div><p class="text-sm text-gray-500">Status</p><p class="font-medium">{{ $this->certificateStatus() }}</p></div>
                    <div><p class="text-sm text-gray-500">Health</p><p class="font-medium">{{ $this->distributionHealth() }}</p></div>
                    <div><p class="text-sm text-gray-500">Last deployment action</p><p class="font-medium">{{ $this->lastAction('acm.') }}</p></div>
                    <div class="flex items-end gap-2">
                        <x-filament::button size="sm" wire:click="requestSsl">Request certificate</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="toggleHttpsEnforcement">Toggle HTTPS</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
