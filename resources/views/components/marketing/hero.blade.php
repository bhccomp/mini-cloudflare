<section class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
        <img
            src="{{ asset('images/hero-banner-new.png') }}"
            alt=""
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

    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 pb-32 pt-40 lg:px-8 lg:pb-32 lg:pt-52">
        <div class="max-w-3xl pt-8">
                <p class="mb-4 inline-flex rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Security Platform</p>
                <h1 class="mb-6 text-balance text-4xl font-semibold leading-[1.06] text-white sm:text-5xl lg:text-6xl">Shield Your Origin. Control Traffic at the Edge.</h1>
                <p class="mt-6 text-lg leading-8 text-slate-200/80">FirePhage combines WAF (Firewall rules), origin IP protection, and availability monitoring in one simple dashboard. No confusing vendor terms. Just protection you can control.</p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-start">
                    <a href="{{ url('/register') }}" class="w-full rounded-xl bg-cyan-400 px-6 py-3 text-center text-sm font-semibold text-slate-950 hover:bg-cyan-300 sm:w-auto">Start Protecting Your Site</a>
                    <a href="#dashboard-preview" class="w-full rounded-xl border border-slate-700 px-6 py-3 text-center text-sm font-semibold text-slate-100 hover:border-slate-500 sm:w-auto">View Dashboard Demo</a>
                </div>

                <div class="mt-6 flex flex-wrap gap-x-3 gap-y-2 text-xs text-slate-400">
                    <span>Origin exposure detection</span>
                    <span class="text-slate-600">|</span>
                    <span>WAF (Firewall rules) + rate limiting</span>
                    <span class="text-slate-600">|</span>
                    <span>Availability + 5xx alerts</span>
                    <span class="text-slate-600">|</span>
                    <span>Simple / Pro views</span>
                </div>
        </div>
    </div>
</section>
