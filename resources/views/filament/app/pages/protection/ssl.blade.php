<x-filament-panels::page>
    <style>
        .fp-ssl-card {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
            gap: 1rem;
        }

        .fp-ssl-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .fp-ssl-tile {
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 0.75rem;
            padding: 0.85rem 0.9rem;
            background: rgba(15, 23, 42, 0.22);
        }

        .fp-ssl-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgb(148, 163, 184);
            margin-bottom: 0.35rem;
        }

        .fp-ssl-value {
            font-size: 1rem;
            font-weight: 600;
            color: rgb(226, 232, 240);
            line-height: 1.35;
            word-break: break-word;
        }

        .fp-ssl-help {
            margin-top: 0.35rem;
            font-size: 0.78rem;
            color: rgb(148, 163, 184);
            line-height: 1.35;
        }

        .fp-ssl-actions {
            border: 1px solid rgba(148, 163, 184, 0.32);
            border-radius: 0.75rem;
            padding: 0.95rem;
            background: rgba(15, 23, 42, 0.3);
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            justify-content: space-between;
        }

        .fp-ssl-actions-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgb(148, 163, 184);
        }

        .fp-ssl-actions-help {
            margin-top: 0.35rem;
            margin-bottom: 0.9rem;
            font-size: 0.82rem;
            color: rgb(148, 163, 184);
            line-height: 1.4;
        }

        .fp-ssl-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }

        @media (max-width: 1100px) {
            .fp-ssl-card {
                grid-template-columns: 1fr;
            }

            .fp-ssl-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

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
                        <div class="fp-ssl-card">
                            <div class="fp-ssl-grid">
                                <section class="fp-ssl-tile">
                                    <p class="fp-ssl-label">Certificate status</p>
                                    <p class="fp-ssl-value">{{ $this->certificateStatus() }}</p>
                                    <p class="fp-ssl-help">Current TLS issuance state.</p>
                                </section>

                                <section class="fp-ssl-tile">
                                    <p class="fp-ssl-label">Transport health</p>
                                    <p class="fp-ssl-value">{{ $this->distributionHealth() }}</p>
                                    <p class="fp-ssl-help">Edge HTTPS readiness.</p>
                                </section>

                                <section class="fp-ssl-tile">
                                    <p class="fp-ssl-label">Deployment</p>
                                    <p class="fp-ssl-value">{{ $this->site->acm_certificate_arn ? 'Certificate requested' : 'Not started' }}</p>
                                    <p class="fp-ssl-help">Provisioning step status.</p>
                                </section>

                                <section class="fp-ssl-tile">
                                    <p class="fp-ssl-label">Last action</p>
                                    <p class="fp-ssl-value">{{ $this->lastAction('acm.') }}</p>
                                    <p class="fp-ssl-help">Most recent SSL operation.</p>
                                </section>
                            </div>

                            <aside class="fp-ssl-actions">
                                <div>
                                    <p class="fp-ssl-actions-title">Actions</p>
                                    <p class="fp-ssl-actions-help">Queue certificate requests and update HTTPS behavior for this site.</p>
                                </div>
                                <div class="fp-ssl-buttons">
                                    <x-filament::button wire:click="requestSsl">Request certificate</x-filament::button>
                                    <x-filament::button color="gray" wire:click="toggleHttpsEnforcement">Toggle HTTPS enforcement</x-filament::button>
                                </div>
                            </aside>
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
