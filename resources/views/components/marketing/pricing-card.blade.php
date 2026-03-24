@props([
    'plan',
    'price',
    'suffix' => '/mo',
    'ctaHref',
    'ctaLabel',
    'featured' => false,
    'badge' => null,
    'description' => null,
    'features' => [],
    'limits' => [],
    'trialDays' => 0,
])

<article @class([
    'relative flex h-full flex-col rounded-2xl p-6',
    'scale-105 border border-cyan-400 bg-slate-900/70 shadow-lg' => $featured,
    'border border-slate-800 bg-slate-900/60' => ! $featured,
])>
    @if($badge)
        <div class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full border border-cyan-300/40 bg-cyan-400 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-950">
            {{ $badge }}
        </div>
    @endif

    <p @class([
        'text-xs uppercase tracking-[0.12em]',
        'text-cyan-300' => $featured,
        'text-slate-400' => ! $featured,
    ])>{{ $plan }}</p>
    <h3 class="mt-2 text-2xl font-semibold text-white">
        {{ $price }}@if($suffix)<span class="text-sm text-slate-400"> {{ $suffix }}</span>@endif
    </h3>

    @if($description)
        <p class="mt-3 text-sm leading-6 text-slate-300">{{ $description }}</p>
    @else
        <p class="mt-3 text-xs font-semibold text-cyan-200">Free assisted onboarding included (we can handle DNS).</p>
    @endif

    @if ((int) $trialDays > 0)
        <p class="mt-3 text-xs font-semibold text-cyan-200">Includes a {{ (int) $trialDays }}-day free trial. Checkout still collects a payment method for automatic billing after the trial unless canceled.</p>
    @endif

    <div class="mt-5 space-y-5">
        @if ($features !== [])
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Features</p>
                <ul class="mt-4 space-y-4 text-sm text-slate-300">
                    @foreach ($features as $feature)
                        <li class="flex items-start gap-3">
                            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-cyan-400/40 bg-cyan-400/10 text-cyan-300">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7 7.06a1 1 0 0 1-1.42 0l-3-3.025a1 1 0 0 1 1.42-1.409L9 11.613l6.296-6.317a1 1 0 0 1 1.408-.006Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($limits !== [])
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Limits</p>
                <ul class="mt-4 space-y-4 text-sm text-slate-300">
                    @foreach ($limits as $limit)
                        <li class="flex items-start gap-3">
                            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-cyan-300">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H4a1 1 0 1 1 0-2h5V4a1 1 0 0 1 1-1Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span>{{ $limit }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (trim((string) $slot) !== '')
            <ul class="hidden">
                {{ $slot }}
            </ul>
        @endif
    </div>

    <div class="mt-auto pt-8">
        <a href="{{ $ctaHref }}" class="block w-full rounded-lg bg-cyan-500 py-3 text-center text-sm font-medium text-slate-950 hover:bg-cyan-400 hover:text-black">{{ $ctaLabel }}</a>
    </div>
</article>
