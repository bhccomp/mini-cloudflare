<x-marketing.section id="features" class="border-y border-slate-800/80 bg-slate-900/40">
        <h2 class="text-3xl font-semibold text-white">Core Security Capabilities</h2>

        <div class="mt-10 grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
            <div class="lg:col-span-5">
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/55 p-3 shadow-[0_18px_40px_rgba(2,8,23,0.4)]">
                    <img src="{{ asset('images/core-capabilities-illustration.svg') }}" alt="Core capabilities illustration" class="h-auto w-full rounded-xl" loading="lazy" decoding="async">
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
</x-marketing.section>
