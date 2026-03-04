<section id="dashboard-preview" class="border-y border-slate-800/80 bg-slate-900/40">
    <div class="mx-auto grid w-full max-w-7xl gap-8 px-6 py-16 lg:grid-cols-12 lg:px-8 lg:py-20">
        <div class="order-2 lg:order-1 lg:col-span-5">
            <h2 class="text-3xl font-semibold text-white">See What You'll Control</h2>
            <p class="mt-3 text-sm text-slate-300">A single dashboard for WAF (Firewall rules), origin IP protection, and monitoring.</p>
            <ul class="mt-6 space-y-3 text-sm text-slate-200">
                <li>Block by country or IP address</li>
                <li>Rate limiting presets</li>
                <li>Origin IP protection status &amp; policy</li>
                <li>Uptime + error spikes (5xx)</li>
            </ul>
        </div>

        <div class="order-1 lg:order-2 lg:col-span-7">
            <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-3 shadow-[0_14px_45px_rgba(2,6,23,0.38)] sm:p-4">
                <div class="w-full overflow-hidden rounded-xl border border-slate-800/90 bg-slate-900 text-left">
                    <picture>
                        <source srcset="{{ asset('images/dashboard-preview.webp') }}" type="image/webp">
                        <img
                            src="{{ asset('images/dashboard-preview.png') }}"
                            alt="FirePhage Security dashboard preview"
                            width="1901"
                            height="959"
                            loading="lazy"
                            decoding="async"
                            class="h-auto w-full object-cover"
                        >
                    </picture>
                </div>

                <div class="mt-4 px-1">
                    <p class="text-sm font-semibold text-white">Live dashboard preview</p>
                    <p class="mt-1 text-xs text-slate-400">Threat summary, traffic map, top countries, and top IPs in one view.</p>
                </div>
            </div>
        </div>
    </div>
</section>
