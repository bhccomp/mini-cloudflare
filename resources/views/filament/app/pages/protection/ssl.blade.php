<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <x-filament::section icon="heroicon-o-lock-closed" heading="Certificate Control" description="Manage HTTPS posture and certificate lifecycle." class="xl:col-span-2">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-emerald-200/40 bg-emerald-500/10 p-4 dark:border-emerald-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Certificate status</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->certificateStatus() }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $this->lastAction('acm.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-cyan-200/40 bg-cyan-500/10 p-4 dark:border-cyan-700/40">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Transport posture</p>
                            <p class="mt-1 text-lg font-semibold">{{ $this->distributionHealth() }}</p>
                            <p class="mt-1 text-sm text-gray-500">Strict HTTPS routing through edge</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button wire:click="requestSsl">Request certificate</x-filament::button>
                        <x-filament::button color="gray" wire:click="toggleHttpsEnforcement">Toggle HTTPS enforcement</x-filament::button>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-clock" heading="Deployment Timeline" description="Recent SSL-related actions.">
                    <div class="space-y-3">
                        <div class="rounded-xl border border-gray-200/70 bg-white/70 p-3 dark:border-gray-800 dark:bg-gray-900/60">
                            <p class="text-sm font-medium">{{ $this->lastAction('acm.') }}</p>
                        </div>
                        <div class="rounded-xl border border-dashed border-gray-300 p-3 text-sm text-gray-500 dark:border-gray-700">
                            More lifecycle details and renewal history are coming soon.
                        </div>
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
