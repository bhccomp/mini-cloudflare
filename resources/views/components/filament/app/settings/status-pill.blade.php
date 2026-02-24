@props([
    'color' => 'gray',
])

<x-filament::badge :color="$color" class="px-2 py-0.5 text-xs font-medium">
    {{ $slot }}
</x-filament::badge>
