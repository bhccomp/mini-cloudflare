@props([
    'items' => [],
    'recommendation' => ['title' => '', 'body' => '', 'color' => 'gray'],
    'showProButton' => false,
])

<x-filament::section
    heading="Simple Security Snapshot"
    description="This simplified view puts the most important protection numbers first. Hover or tap the ? icons to see what each field means."
    icon="heroicon-o-shield-check"
>
    <div class="space-y-5">
        <div @class([
            'rounded-2xl border px-4 py-3 text-sm',
            'border-danger-200 bg-danger-50 text-danger-950 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-100' => $recommendation['color'] === 'danger',
            'border-warning-200 bg-warning-50 text-warning-950 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-100' => $recommendation['color'] === 'warning',
            'border-success-200 bg-success-50 text-success-950 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-100' => $recommendation['color'] === 'success',
            'border-gray-300 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900/80 dark:text-gray-200' => ! in_array($recommendation['color'], ['danger', 'warning', 'success'], true),
        ])>
            <p class="font-semibold">{{ $recommendation['title'] }}</p>
            <p class="mt-1">{{ $recommendation['body'] }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                <x-filament::section compact secondary>
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ $item['label'] }}
                                </div>
                                <div @class([
                                    'text-2xl font-semibold tracking-tight',
                                    'text-danger-600 dark:text-danger-400' => $item['color'] === 'danger',
                                    'text-warning-600 dark:text-warning-400' => $item['color'] === 'warning',
                                    'text-success-600 dark:text-success-400' => $item['color'] === 'success',
                                    'text-primary-600 dark:text-primary-400' => $item['color'] === 'primary',
                                    'text-gray-900 dark:text-white' => $item['color'] === 'gray',
                                ])>
                                    {{ $item['value'] }}
                                </div>
                            </div>

                            <x-filament.app.help-tooltip :text="$item['help']" />
                        </div>

                        <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                            {{ $item['support'] }}
                        </p>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>

    @if ($showProButton)
        <x-slot name="footer">
            <x-filament::actions alignment="end">
                <x-filament::button wire:click="switchToProMode" color="gray">
                    Switch to Pro for deeper detail
                </x-filament::button>
            </x-filament::actions>
        </x-slot>
    @endif
</x-filament::section>
