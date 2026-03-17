@props([
    'items' => [],
    'recommendation' => ['title' => '', 'body' => '', 'color' => 'gray'],
    'showProButton' => false,
])

<x-filament::section
    heading="Simple Site Overview"
    description="A beginner-friendly summary of the main FirePhage services for this site: protection, availability, billing, delivery, cache, SSL, and WordPress."
    icon="heroicon-o-squares-2x2"
>
    <div class="space-y-5">
        <div @class([
            'rounded-2xl border px-4 py-3 text-sm',
            'border-primary-500/30 bg-primary-500/10 text-primary-100' => $recommendation['color'] === 'primary',
            'border-danger-500/30 bg-danger-500/10 text-danger-100' => $recommendation['color'] === 'danger',
            'border-warning-500/30 bg-warning-500/10 text-warning-100' => $recommendation['color'] === 'warning',
            'border-success-500/30 bg-success-500/10 text-success-100' => $recommendation['color'] === 'success',
            'border-gray-300 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900/80 dark:text-gray-200' => ! in_array($recommendation['color'], ['primary', 'danger', 'warning', 'success'], true),
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
