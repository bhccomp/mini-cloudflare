<x-marketing.section>
    <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
        <div>
            <h2 class="text-3xl font-semibold text-white">A dashboard built for humans</h2>
            <p class="mt-3 text-sm text-slate-300">Clear summaries, not security jargon.</p>

            <ul class="mt-6 space-y-3 text-sm text-slate-200">
                <li>Plain-language threat summary (what happened, why it matters)</li>
                <li>Top sources: countries, IPs, paths</li>
                <li>Simple actions: block, rate limit, challenge</li>
                <li>Useful alerts (not 500 notifications)</li>
            </ul>

            <div class="mt-6 rounded-2xl border border-cyan-400/25 bg-cyan-500/10 px-5 py-4 text-sm text-cyan-100">
                Example: &ldquo;Blocked 339 bot requests from 55 IPs targeting /wp-login.php&rdquo;
            </div>
        </div>

        <div class="relative">
            <div class="pointer-events-none absolute -inset-4 rounded-[36px] bg-[radial-gradient(circle_at_60%_40%,rgba(34,211,238,0.16),transparent_60%)]" aria-hidden="true"></div>
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-slate-950/70 p-3 shadow-[0_18px_50px_rgba(2,6,23,0.42)] sm:p-4">
                <div class="w-full overflow-hidden rounded-xl border border-slate-800/90 bg-slate-900">
                    <picture>
                        <source srcset="{{ asset('images/dashboard-preview.webp') }}" type="image/webp">
                        <img
                            src="{{ asset('images/dashboard-preview.png') }}"
                            alt="FirePhage dashboard screenshot"
                            width="1901"
                            height="959"
                            loading="lazy"
                            decoding="async"
                            class="h-auto w-full object-cover"
                        >
                    </picture>
                </div>
                <div class="absolute bottom-3 left-4 rounded-full border border-cyan-300/30 bg-slate-900/85 px-3 py-1 text-xs text-cyan-100">
                    Example: Blocked 339 bot requests from 55 IPs
                </div>
            </div>
        </div>
    </div>
</x-marketing.section>
