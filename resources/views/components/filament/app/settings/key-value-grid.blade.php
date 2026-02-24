@props([
    'rows' => [],
])

<div class="grid gap-2 sm:grid-cols-1 xl:grid-cols-2">
    @foreach ($rows as $row)
        @php
            $value = data_get($row, 'value');
            $isHtml = (bool) data_get($row, 'is_html', false);
        @endphp
        <div class="rounded-xl border border-gray-200/70 bg-gray-50/70 px-3 py-2 dark:border-gray-800 dark:bg-gray-900/60">
            <div class="flex items-start justify-between gap-3">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ data_get($row, 'label') }}</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 text-right break-all">
                    @if ($isHtml)
                        {!! $value !!}
                    @else
                        {{ $value }}
                    @endif
                </span>
            </div>
        </div>
    @endforeach
</div>
