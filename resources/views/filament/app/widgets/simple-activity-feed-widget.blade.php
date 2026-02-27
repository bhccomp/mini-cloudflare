<x-filament::section
    heading="Recent Activity"
    description="Plain-language summary of what protection has been doing lately."
    icon="heroicon-o-bolt"
>
    <div class="space-y-3">
        @forelse ($items as $item)
            <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                <p>{{ $item['message'] }}</p>
                @if ($item['at'])
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['at']->diffForHumans() }}</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-600 dark:text-gray-300">No activity available yet.</p>
        @endforelse
    </div>
</x-filament::section>

