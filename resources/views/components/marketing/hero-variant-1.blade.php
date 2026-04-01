<section class="relative overflow-hidden pb-6 lg:pb-8">
    <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
        <img
            src="{{ asset('images/hero-banner-new.png') }}"
            alt="Abstract FirePhage network protection background"
            class="absolute inset-0 h-full w-full object-cover object-right opacity-[0.85]"
            loading="eager"
            decoding="async"
            aria-hidden="true"
        >
        <div
            class="absolute inset-0"
            style="background: linear-gradient(90deg, rgba(2,8,23,0.95) 0%, rgba(2,8,23,0.85) 35%, rgba(2,8,23,0.4) 65%, rgba(2,8,23,0.1) 100%);"
        ></div>
    </div>

    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 pb-12 pt-28 lg:px-8 lg:pb-14 lg:pt-40">
        <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-2 lg:gap-12">
            <div class="max-w-3xl pt-8">
                    <p class="mb-4 inline-flex rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Security Platform</p>
                    <h1 class="mb-5 text-balance text-4xl font-semibold leading-[1.06] text-white sm:text-5xl lg:text-6xl">Edge protection for WordPress and WooCommerce, without the usual security noise</h1>
                    <p class="text-lg leading-8 text-slate-200/85">
                        Stop hostile traffic at the edge, reduce origin pressure, and keep operations clear when traffic turns ugly.
                    </p>
                    <p class="mt-3 text-sm text-slate-300/90">Start with full edge protection on a free trial, or connect the lighter WordPress entry path first.</p>

                    <ul class="mt-6 space-y-2 text-sm text-slate-200/90">
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Edge protection requires DNS onboarding, and we can handle cutover with you</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Strong WooCommerce and bot-pressure workflows when traffic turns hostile</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Human-readable dashboard with WordPress visibility connected into it</span></li>
                    </ul>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-start">
                        <x-marketing.auth-aware-link guest-label="Start 30-day free trial" class="w-full rounded-xl bg-cyan-400 px-6 py-3 text-center text-sm font-semibold text-slate-950 shadow-[0_0_0_1px_rgba(34,211,238,0.24),0_0_22px_rgba(34,211,238,0.22)] hover:bg-cyan-300 sm:w-auto" />
                        <a href="{{ $demoDashboardUrl ?? ('https://' . config('demo.host') . '/app') }}" class="w-full rounded-xl border border-slate-700 px-6 py-3 text-center text-sm font-semibold text-slate-100 hover:border-slate-500 sm:w-auto">View live demo</a>
                    </div>
                    <p class="mt-3 text-xs text-slate-400/90">30-day free trial • One site • Full protection</p>
                    <p class="mt-1 text-xs text-slate-500/90">Already protecting live WordPress and WooCommerce sites</p>

                    <div class="mt-6 flex flex-wrap gap-x-3 gap-y-2 text-xs text-slate-400">
                        <span>Origin protection</span>
                        <span class="text-slate-600">|</span>
                        <span>Edge firewall rules</span>
                        <span class="text-slate-600">|</span>
                        <span>Bot + WooCommerce workflows</span>
                        <span class="text-slate-600">|</span>
                        <span>Live monitoring</span>
                    </div>
            </div>

            <div class="relative flex justify-center lg:justify-end">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_60%_45%,rgba(124,58,237,0.22),transparent_62%)] blur-3xl opacity-70" aria-hidden="true"></div>
                <div class="hero-laptop relative z-10">
                    <img
                        src="{{ asset('design-assets/dashboard-laptop.png') }}"
                        alt="FirePhage dashboard preview on laptop"
                        class="hero-visual-laptop"
                        width="1536"
                        height="1024"
                        loading="eager"
                        decoding="async"
                    >
                </div>
            </div>
        </div>

        <div class="mt-6 mb-0 pb-0 text-center lg:mt-7 lg:pb-1">
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-cyan-200/90">Perfect for</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                @foreach (['WordPress websites', 'WooCommerce stores', 'Agency-managed sites', 'High-traffic blogs'] as $item)
                    <span class="rounded-full border border-white/10 bg-slate-900/65 px-4 py-2 text-sm text-slate-200">{{ $item }}</span>
                @endforeach
            </div>

            <div class="mt-4 flex justify-center">
                <span class="rounded-full border border-white/10 bg-slate-900/65 px-5 py-2 text-sm text-slate-200">
                    Already protecting 40+ WordPress websites • 3.2 million requests filtered last month • 99.9% attack mitigation
                </span>
            </div>
        </div>
    </div>
</section>
