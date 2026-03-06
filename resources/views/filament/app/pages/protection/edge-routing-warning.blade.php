@if ($this->site && $this->shouldShowEdgeRoutingWarning())
    @php
        $isPartial = ($this->edgeRoutingStatus()['status'] ?? null) === 'partial';
        $toneClasses = $isPartial
            ? 'border-warning-200 bg-warning-50/90 text-warning-950 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-100'
            : 'border-danger-200 bg-danger-50/90 text-danger-950 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-100';
        $detailsClasses = $isPartial
            ? 'border-warning-300/70 bg-white/70 dark:border-warning-500/30 dark:bg-white/5'
            : 'border-danger-300/70 bg-white/70 dark:border-danger-500/30 dark:bg-white/5';
    @endphp

    <div class="rounded-xl border p-4 shadow-sm {{ $toneClasses }}">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 rounded-full p-2 {{ $isPartial ? 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-200' : 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-200' }}">
                    <x-filament::icon
                        :icon="$isPartial ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-shield-exclamation'"
                        class="h-5 w-5"
                    />
                </div>

                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold">Protection Warning</h3>
                        <x-filament::badge :color="$this->edgeRoutingWarningColor()">
                            {{ $isPartial ? 'Partially Protected' : 'Protection Inactive' }}
                        </x-filament::badge>
                    </div>

                    <p class="text-sm leading-6">
                        {{ $this->edgeRoutingWarningMessage() }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-end">
                <x-filament::button
                    color="gray"
                    size="sm"
                    wire:click="refreshEdgeRoutingStatus"
                    wire:loading.attr="disabled"
                    wire:target="refreshEdgeRoutingStatus"
                >
                    Refresh status
                </x-filament::button>
            </div>
        </div>

        @if ($this->edgeRoutingRecords() !== [])
            <details class="mt-4 overflow-hidden rounded-lg border {{ $detailsClasses }}">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium">
                    <span>Show DNS records to restore protection</span>
                    <span class="text-xs opacity-75">Click to expand</span>
                </summary>

                <div class="grid gap-3 border-t border-black/5 px-4 py-4 dark:border-white/10 lg:grid-cols-2">
                    @foreach ($this->edgeRoutingRecords() as $record)
                        <div class="rounded-lg border border-black/10 bg-white/80 p-3 text-sm text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                            <p><strong>Host:</strong> {{ data_get($record, 'name', data_get($record, 'host')) }}</p>
                            <p><strong>Type:</strong> {{ data_get($record, 'type', 'CNAME') }}</p>
                            <p class="break-all"><strong>Value:</strong> {{ data_get($record, 'value') }}</p>
                            <p><strong>TTL:</strong> {{ data_get($record, 'ttl', 'Auto') }}</p>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endif
