@props([
    'title',
    'description' => null,
    'status' => null,
    'statusColor' => 'gray',
    'icon' => null,
])

<article class="rounded-2xl border border-gray-200/70 bg-white/85 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/75 md:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                @if ($icon)
                    <x-filament::icon :icon="$icon" class="h-5 w-5 text-gray-500" />
                @endif
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ $title }}
                </h3>
            </div>
            @if ($description)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $description }}
                </p>
            @endif
        </div>

        @if ($status)
            <x-filament.app.settings.status-pill :color="$statusColor">
                {{ $status }}
            </x-filament.app.settings.status-pill>
        @endif
    </div>

    <div class="mt-5 space-y-4">
        {{ $slot }}
    </div>
</article>
