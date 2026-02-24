@props([
    'title',
    'description' => null,
    'status' => null,
    'statusColor' => 'gray',
    'icon' => null,
])

<x-filament::section :heading="$title" :description="$description" :icon="$icon" compact>
    @if ($status)
        <x-slot name="afterHeader">
            <x-filament.app.settings.status-pill :color="$statusColor">
                {{ $status }}
            </x-filament.app.settings.status-pill>
        </x-slot>
    @endif

    {{ $slot }}

    @isset($actions)
        <x-slot name="footer">
            <x-filament::actions alignment="end">
                {{ $actions }}
            </x-filament::actions>
        </x-slot>
    @endisset
</x-filament::section>
