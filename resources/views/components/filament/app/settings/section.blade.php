@props([
    'title',
    'description' => null,
    'status' => null,
    'statusColor' => 'gray',
    'icon' => null,
])

<section class="border-t border-gray-200/70 pt-4 first:border-t-0 first:pt-0 dark:border-gray-800">
    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                @if ($icon)
                    <x-filament::icon :icon="$icon" class="h-4 w-4 text-gray-500" />
                @endif
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ $title }}
                </h4>
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

    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
        <div>
            {{ $slot }}
        </div>

        @isset($actions)
            <div class="w-full lg:justify-self-end lg:text-right">
                {{ $actions }}
            </div>
        @endisset
    </div>
</section>
