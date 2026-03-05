<section class="border-y border-white/5 bg-slate-900/35 py-10">
    <div class="mx-auto max-w-7xl px-6 text-center">
        <p class="text-sm font-semibold text-slate-200">Built for teams protecting real sites</p>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
            @foreach (['WordPress', 'WooCommerce', 'Laravel', 'APIs', 'E-commerce', 'High-traffic sites'] as $badge)
                <span class="rounded-full border border-white/10 bg-slate-900/70 px-4 py-2 text-sm text-slate-200">{{ $badge }}</span>
            @endforeach
        </div>
    </div>
</section>
