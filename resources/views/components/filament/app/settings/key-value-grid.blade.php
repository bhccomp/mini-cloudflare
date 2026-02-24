@props([
    'rows' => [],
])

<div class="grid gap-3 md:grid-cols-2">
    @foreach ($rows as $row)
        @php
            $value = data_get($row, 'value');
            $isHtml = (bool) data_get($row, 'is_html', false);
        @endphp
        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ data_get($row, 'label') }}</p>
            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100 break-all">
                @if ($isHtml)
                    {!! $value !!}
                @else
                    {{ $value }}
                @endif
            </div>
        </div>
    @endforeach
</div>
