<section class="py-16 lg:py-20">
    <div class="mx-auto max-w-6xl px-6">
        <div class="grid grid-cols-1 items-center gap-10 md:grid-cols-12">
            <div class="order-2 md:order-1 md:col-span-5">
                <h2 class="text-3xl font-semibold text-white">Safe DNS Cutover</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    When you&apos;re ready, simply switch your DNS to FirePhage Edge.
                    The platform verifies records automatically and ensures a clean traffic cutover.
                </p>

                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>DNS verification before activation</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Automatic traffic validation</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>No downtime deployment</span></li>
                </ul>
            </div>

            <div class="order-1 md:order-2 md:col-span-7">
                <img
                    src="{{ asset('design-assets/dns-cutover-ui.png') }}"
                    alt="DNS cutover interface illustration"
                    class="mx-auto w-full max-w-[720px]"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
