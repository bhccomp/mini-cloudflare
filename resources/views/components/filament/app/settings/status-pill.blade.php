@props([
    'color' => 'gray',
])

<x-filament::badge :color="$color">
    {{ $slot }}
</x-filament::badge>
