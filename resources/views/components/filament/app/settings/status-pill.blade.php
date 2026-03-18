@props([
    'color' => 'gray',
])

<x-filament::badge class="fp-pro-status-pill" :color="$color">
    {{ $slot }}
</x-filament::badge>
