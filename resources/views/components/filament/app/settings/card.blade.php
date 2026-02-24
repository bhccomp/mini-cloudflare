@props([
    'title',
    'description' => null,
    'status' => null,
    'statusColor' => 'gray',
    'icon' => null,
])

<x-filament::section :heading="$title" :description="$description" :icon="$icon">
    <div class="space-y-4">
        @if ($status)
            <div class="flex items-center justify-end">
                <x-filament.app.settings.status-pill :color="$statusColor">
                    {{ $status }}
                </x-filament.app.settings.status-pill>
            </div>
        @endif

        <div class="space-y-4">
            {{ $slot }}
        </div>
    </div>
</x-filament::section>
