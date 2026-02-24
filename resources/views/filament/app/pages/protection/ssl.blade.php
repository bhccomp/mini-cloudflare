<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <x-filament.app.settings.card
                        title="SSL / TLS Settings"
                        description="Manage certificate lifecycle and HTTPS transport controls."
                        icon="heroicon-o-lock-closed"
                        :status="$this->certificateStatus()"
                        :status-color="$this->site->acm_certificate_arn ? 'success' : 'warning'"
                    >
                        <div class="grid gap-4 lg:grid-cols-3">
                            <section class="rounded-xl border border-emerald-200/60 bg-emerald-50/70 p-4 dark:border-emerald-700/40 dark:bg-emerald-900/20">
                                <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Certificate</p>
                                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->certificateStatus() }}</p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Current TLS issuance state</p>
                            </section>

                            <section class="rounded-xl border border-cyan-200/60 bg-cyan-50/70 p-4 dark:border-cyan-700/40 dark:bg-cyan-900/20">
                                <p class="text-xs font-medium uppercase tracking-wide text-cyan-700 dark:text-cyan-300">Transport Health</p>
                                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->distributionHealth() }}</p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Edge HTTPS readiness</p>
                            </section>

                            <section class="rounded-xl border border-indigo-200/60 bg-indigo-50/70 p-4 dark:border-indigo-700/40 dark:bg-indigo-900/20 lg:row-span-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Actions</p>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Queue certificate operations and update transport policy.</p>
                                <div class="mt-4 flex flex-col gap-2">
                                    <x-filament::button wire:click="requestSsl">Request certificate</x-filament::button>
                                    <x-filament::button color="gray" wire:click="toggleHttpsEnforcement">Toggle HTTPS enforcement</x-filament::button>
                                </div>
                            </section>

                            <section class="rounded-xl border border-amber-200/60 bg-amber-50/70 p-4 dark:border-amber-700/40 dark:bg-amber-900/20">
                                <p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Deployment</p>
                                <p class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $this->site->acm_certificate_arn ? 'Certificate requested' : 'Not started' }}
                                </p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Provisioning step status</p>
                            </section>

                            <section class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/70">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Action</p>
                                <p class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $this->lastAction('acm.') }}</p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Most recent SSL operation</p>
                            </section>
                        </div>
                    </x-filament.app.settings.card>
                </div>

                <x-filament.app.settings.card
                    title="Deployment Timeline"
                    description="Recent SSL actions"
                    icon="heroicon-o-clock"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Recent event', 'value' => $this->lastAction('acm.')],
                        ['label' => 'Renewal history', 'value' => 'Coming soon'],
                    ]" />
                </x-filament.app.settings.card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
