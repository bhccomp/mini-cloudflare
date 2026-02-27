@if ($this->isProMode())
<div x-data="{ openTech: false }" style="display:flex; justify-content:flex-end; margin-bottom:0.5rem;">
    <button type="button" x-on:click="openTech = true" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        Technical Details
    </button>

    <div x-show="openTech" x-cloak style="position:fixed; inset:0; z-index:50;" x-on:keydown.escape.window="openTech = false">
        <div style="position:absolute; inset:0; background:rgba(15,23,42,0.45);" x-on:click="openTech = false"></div>
        <div style="position:absolute; top:0; right:0; height:100%; width:min(420px,100%); background:white; border-left:1px solid var(--gray-200); padding:1rem; overflow:auto;" class="dark:!bg-gray-900 dark:!border-gray-800">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <h3 style="font-weight:600;">Diagnostics</h3>
                <x-filament::button color="gray" size="xs" x-on:click="openTech = false">Close</x-filament::button>
            </div>

            @php($diag = $this->diagnosticsDetails())
            <div class="grid gap-2 text-sm">
                <div><strong>Edge Provider:</strong> {{ $diag['edge_provider'] ?? 'n/a' }}</div>
                <div><strong>Zone ID / Pull Zone ID:</strong> {{ $diag['zone_id'] ?? 'n/a' }}</div>
                <div><strong>Site ID:</strong> {{ $diag['site_id'] ?? 'n/a' }}</div>
                <div><strong>Last sync timestamp:</strong> {{ $diag['last_sync'] ?? 'n/a' }}</div>
                <div><strong>API status:</strong> {{ $diag['api_status'] ?? 'n/a' }}</div>
                <div><strong>Last API response time:</strong> {{ $diag['api_response_time'] ?? 'n/a' }}</div>
                <div><strong>Raw provider health state:</strong> {{ $diag['raw_health'] ?? 'n/a' }}</div>
            </div>
        </div>
    </div>
</div>
@endif
