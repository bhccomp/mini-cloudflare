<section class="relative w-full border-y border-white/5 bg-[#041427] py-28">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div class="order-1 lg:order-1 relative flex justify-center lg:justify-start">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_35%_45%,rgba(59,130,246,0.22),transparent_62%)] blur-3xl opacity-65" aria-hidden="true"></div>
                <img
                    src="{{ asset('design-assets/dns-cutover-ui.png') }}"
                    alt="DNS cutover interface illustration"
                    class="feature-illustration feature-illustration--dns relative z-10 rounded-none"
                    width="1536"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                >
            </div>

            <div class="order-2 lg:order-2">
                <h2 class="text-3xl font-semibold text-white">Safe DNS Cutover</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    DNS cutover should feel controlled, not risky. FirePhage verifies the edge setup before activation, lets teams preview the state, and keeps the transition staged so traffic is not moved blind.
                </p>

                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Preview the target state before activation</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Verify records and edge readiness before traffic is shifted</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Keep rollback optional if something does not look right</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Move traffic in a staged, controlled way</span></li>
                </ul>
            </div>
        </div>
    </div>
</section>
