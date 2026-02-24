@props([
    'title',
    'description' => null,
    'status' => null,
    'statusColor' => 'gray',
    'icon' => null,
])

<x-filament::section :heading="$title" :description="$description" :icon="$icon" :divided="true">
    @if ($status)
        <x-slot name="afterHeader">
            <x-filament.app.settings.status-pill :color="$statusColor">
                {{ $status }}
            </x-filament.app.settings.status-pill>
        </x-slot>
    @endif

    {{ $slot }}
</x-filament::section>
