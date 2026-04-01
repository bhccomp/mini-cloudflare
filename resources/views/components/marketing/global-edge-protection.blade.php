<section class="relative w-full border-y border-white/5 bg-[#020817] py-28">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_22%_36%,rgba(34,211,238,0.09),transparent_42%)]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">WooCommerce + Bot Pressure</p>
                <h2 class="mt-4 text-3xl font-semibold text-white">When stores get hit by fake orders, login abuse, and scraping, generic protection stops being enough.</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    WooCommerce traffic breaks in specific ways. Fake orders waste time, bots hammer login and account flows, scrapers drain origin capacity, and checkout disruption hits revenue directly. FirePhage gives store teams a faster path into stronger protection when that pressure starts building.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Protect checkout, account, cart, and store API flows with stronger defaults</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Clamp fake-order pressure, scraping, and login abuse before it spreads deeper into the store</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Apply FirePhage presets like High Bot Pressure when store traffic turns hostile</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Keep checkout and operator visibility cleaner while the edge does the defensive work early</span></li>
                </ul>

                <a href="{{ route('services.show', 'bot-protection') }}" class="mt-8 inline-flex rounded-lg border border-cyan-400/40 bg-cyan-500/15 px-5 py-3 text-sm font-semibold text-cyan-100 hover:border-cyan-300/70 hover:text-white">
                    See bot and WooCommerce workflows
                </a>
            </div>

            <div class="relative">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_45%,rgba(59,130,246,0.20),transparent_65%)] blur-3xl opacity-60" aria-hidden="true"></div>
                <img
                    src="{{ asset('design-assets/world-map-security.png') }}"
                    alt="Global edge protection map illustration"
                    class="feature-illustration feature-illustration--map relative z-10"
                    width="1536"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
