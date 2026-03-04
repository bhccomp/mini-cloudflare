@props([
    'plan',
    'price',
    'suffix' => '/mo',
    'ctaHref',
    'ctaLabel',
    'featured' => false,
])

<article @class([
    'flex h-full flex-col rounded-2xl p-6',
    'border border-cyan-400/45 bg-slate-900/70 shadow-[0_0_0_1px_rgba(34,211,238,0.2)]' => $featured,
    'border border-slate-800 bg-slate-900/60' => ! $featured,
])>
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

    <a href="{{ $ctaHref }}" class="mt-auto pt-8 inline-flex rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300">{{ $ctaLabel }}</a>
</article>
