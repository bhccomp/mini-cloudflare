<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="rounded-2xl bg-gradient-to-r from-primary-500/20 via-primary-500/10 to-transparent p-6 ring-1 ring-primary-500/20 dark:from-primary-500/15 dark:via-primary-500/5">
                <p class="text-xs uppercase tracking-wider text-gray-500">SSL/TLS</p>
                <h2 class="mt-1 text-2xl font-semibold">{{ $this->site->apex_domain }}</h2>
            </div>

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
