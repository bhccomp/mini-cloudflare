<section id="features" class="relative w-full border-y border-white/5 bg-[#041427]">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 py-28">
        <h2 class="text-3xl font-semibold text-white">Explore the FirePhage stack</h2>
        <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300">Use the homepage to understand the product quickly, then go deeper from the service pages when you want the full explanation.</p>

        <div class="mt-10 grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
            <div class="lg:col-span-5">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_45%_45%,rgba(59,130,246,0.2),transparent_65%)] blur-3xl opacity-60" aria-hidden="true"></div>
                    <img
                        src="{{ asset('images/core-capabilities-illustration.svg') }}"
                        alt="Core capabilities illustration"
                        class="relative z-10 mx-auto h-auto w-full max-w-[1100px] rounded-none shadow-none"
                        width="1200"
                        height="760"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:col-span-7">
                @foreach ([
                    ['title' => 'WordPress WAF', 'description' => 'Stop attacks before they hit your server.', 'route' => route('services.show', 'waf')],
                    ['title' => 'Bot Protection', 'description' => 'Block login abuse, scraping, and noisy traffic.', 'route' => route('services.show', 'bot-protection')],
                    ['title' => 'WooCommerce Protection', 'description' => 'Protect checkout, account, and store flows.', 'route' => route('services.show', 'bot-protection')],
                    ['title' => 'CDN', 'description' => 'Deliver faster with less origin pressure.', 'route' => route('services.show', 'cdn')],
                    ['title' => 'Uptime Monitoring', 'description' => 'See issues before users report them.', 'route' => route('services.show', 'uptime-monitor')],
                    ['title' => 'Under Attack Mode', 'description' => 'Escalate protection when traffic turns hostile.', 'route' => route('services.show', 'ddos-protection')],
                ] as $item)
                    <a href="{{ $item['route'] }}" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-5 transition duration-200 hover:-translate-y-1 hover:border-cyan-300/45">
                        <p class="text-lg font-semibold text-white transition group-hover:text-cyan-200">{{ $item['title'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-300">{{ $item['description'] }}</p>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-[0.12em] text-cyan-300">Open service page</p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>
