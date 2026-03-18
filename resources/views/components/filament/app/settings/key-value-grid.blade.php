@props([
    'rows' => [],
])

<div class="fp-pro-grid" style="display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    @foreach ($rows as $row)
        @php
            $value = data_get($row, 'value');
            $isHtml = (bool) data_get($row, 'is_html', false);
        @endphp
        <x-filament::section compact secondary class="fp-pro-metric">
            <div class="fp-pro-metric-body" style="display: grid; gap: 0.5rem;">
                <x-filament::badge color="gray" class="fp-pro-metric-label">
                    {{ data_get($row, 'label') }}
                </x-filament::badge>

                <div class="fp-pro-metric-value">
                    @if ($isHtml)
                        {!! $value !!}
                    @else
                        {{ $value }}
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endforeach
</div>
