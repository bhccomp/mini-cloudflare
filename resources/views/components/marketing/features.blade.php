<section id="features" class="relative w-full border-y border-white/5 bg-[#041427]">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 py-28">
        <h2 class="text-3xl font-semibold text-white">Core Security Capabilities</h2>

        <div class="mt-10 grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
            <div class="lg:col-span-5">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_45%_45%,rgba(59,130,246,0.2),transparent_65%)] blur-3xl opacity-60" aria-hidden="true"></div>
                    <img
                        src="{{ asset('images/core-capabilities-illustration.svg') }}"
                        alt="Core capabilities illustration"
                        class="relative z-10 mx-auto h-auto w-full max-w-[1100px] rounded-none shadow-none"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:col-span-7">
                <x-marketing.feature-card
                    icon="traffic"
                    title="Edge Traffic Controls"
                    description="Limit abusive traffic with country and IP blocking, plus practical rate-limit presets."
                />

                <x-marketing.feature-card
                    icon="origin"
                    title="Origin Protection"
                    description="Protect your origin IP, monitor origin health, and keep policy status visible."
                />

                <x-marketing.feature-card
                    icon="monitoring"
                    title="Monitoring & Detection"
                    description="See abnormal traffic patterns, uptime checks, and event spikes in one timeline."
                />

                <x-marketing.feature-card
                    icon="alerts"
                    title="Alerting & Operations"
                    description="Get meaningful alerts and move from detection to action quickly."
                />
            </div>
        </div>
    </div>
</section>
