<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-map" heading="Traffic Regions" description="Regional request distribution and risk posture.">
        <div class="grid gap-4 lg:grid-cols-5">
            <div class="rounded-2xl border border-gray-200/70 bg-gradient-to-br from-cyan-500/10 via-transparent to-blue-500/10 p-5 dark:border-gray-700/60">
                <p class="text-xs uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Map View</p>
                <div class="mt-4 space-y-3">
                    @foreach ($regions as $region)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $region['name'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $region['share'] }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-200/80 dark:bg-gray-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-blue-500" style="width: {{ $region['share'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($regions as $region)
                        <article class="rounded-2xl border border-gray-200/70 bg-white/80 p-4 dark:border-gray-800 dark:bg-gray-900/70">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $region['name'] }}</h4>
                                @php
                                    $threatColor = match ($region['threat']) {
                                        'Low' => 'success',
                                        'Medium' => 'warning',
                                        default => 'danger',
                                    };
                                @endphp
                                <x-filament::badge :color="$threatColor">{{ $region['threat'] }}</x-filament::badge>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $region['share'] }}%</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Traffic share</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
