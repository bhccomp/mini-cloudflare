<section class="relative w-full border-y border-white/5 bg-[#020817] py-28">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-semibold text-white">Global Edge Protection</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    FirePhage routes incoming traffic through a distributed edge layer where automated filtering and Web Application Firewall rules inspect requests before they ever reach your infrastructure. Malicious traffic is stopped early, while legitimate users connect normally.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Inspect traffic at the edge before it reaches your origin</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Automatically filter common web attacks and bot traffic</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Reduce load on your application servers</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Protect APIs, web apps, and dynamic endpoints</span></li>
                </ul>
            </div>

            <div class="relative">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_45%,rgba(59,130,246,0.20),transparent_65%)] blur-3xl opacity-60" aria-hidden="true"></div>
                <img
                    src="{{ asset('design-assets/world-map-security.png') }}"
                    alt="Global edge protection map illustration"
                    class="relative z-10 mx-auto w-full max-w-[1200px]"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
