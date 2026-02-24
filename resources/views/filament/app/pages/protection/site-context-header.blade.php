@if ($this->site)
    <div class="mb-4 flex items-center gap-2">
        <h2 class="text-lg font-semibold">{{ $this->site->display_name }}</h2>
        <span class="text-sm text-gray-500">{{ $this->site->apex_domain }}</span>
        <x-filament::badge :color="$this->badgeColor()">{{ str($this->site->status)->replace('_', ' ')->title() }}</x-filament::badge>
    </div>
@endif
