@if ($this->site)
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold">{{ $this->site->apex_domain }}</h2>
            <x-filament::badge :color="$this->badgeColor()">{{ $this->statusLabel() }}</x-filament::badge>
        </div>
        <span class="text-xs text-gray-500">Protection controls</span>
    </div>
@endif
