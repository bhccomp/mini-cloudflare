@props([
    'plan',
    'price',
    'suffix' => '/mo',
    'ctaHref',
    'ctaLabel',
    'featured' => false,
])

<article @class([
    'relative flex h-full flex-col rounded-2xl p-6',
    'scale-105 border border-cyan-400 bg-slate-900/70 shadow-lg' => $featured,
    'border border-slate-800 bg-slate-900/60' => ! $featured,
])>
    @if($featured)
        <div class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full border border-cyan-300/40 bg-cyan-400 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-950">
            Most Popular
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

    <p class="mt-3 text-xs font-semibold text-cyan-200">Free assisted onboarding included (we can handle DNS).</p>

    <ul class="mt-5 space-y-2 text-sm text-slate-300">
        {{ $slot }}
    </ul>

    <a href="{{ $ctaHref }}" class="mt-auto w-full rounded-lg bg-cyan-500 py-3 text-center text-sm font-medium text-slate-950 hover:bg-cyan-400 hover:text-black">{{ $ctaLabel }}</a>
</article>
