<section id="pricing" class="mx-auto w-full max-w-7xl px-6 py-16 lg:px-8 lg:py-20">
    <div class="mb-8 max-w-2xl">
        <h2 class="text-3xl font-semibold text-white">Pricing</h2>
        <p class="mt-3 text-sm text-slate-300">Clear plans with practical differences. No unclear feature gating.</p>
        <p class="mt-3 inline-flex rounded-md border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold text-cyan-200">Free assisted onboarding included (we can handle DNS).</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <article class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            <p class="text-xs uppercase tracking-[0.12em] text-slate-400">Simple</p>
            <h3 class="mt-2 text-2xl font-semibold text-white">$XX<span class="text-sm text-slate-400"> /mo</span></h3>
            <p class="mt-3 text-xs font-semibold text-cyan-200">Free assisted onboarding included (we can handle DNS).</p>
            <ul class="mt-5 space-y-2 text-sm text-slate-300">
                <li>1 domain</li>
                <li>Basic WAF (Firewall rules) settings (country/IP blocks, rate limits)</li>
                <li>Monitoring every 5 minutes</li>
                <li>Standard analytics</li>
            </ul>
            <a href="{{ url('/register') }}" class="mt-8 inline-flex rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300">Get Started</a>
        </article>

        <article class="rounded-2xl border border-cyan-400/45 bg-slate-900/70 p-6 shadow-[0_0_0_1px_rgba(34,211,238,0.2)]">
            <p class="text-xs uppercase tracking-[0.12em] text-cyan-300">Pro</p>
            <h3 class="mt-2 text-2xl font-semibold text-white">$XX<span class="text-sm text-slate-400"> /mo</span></h3>
            <p class="mt-3 text-xs font-semibold text-cyan-200">Free assisted onboarding included (we can handle DNS).</p>
            <ul class="mt-5 space-y-2 text-sm text-slate-300">
                <li>Up to 10 domains</li>
                <li>Advanced WAF (Firewall rules) settings + access control sets</li>
                <li>Monitoring every 1 minute</li>
                <li>Origin IP protection + origin health signals</li>
                <li>Priority alerts</li>
            </ul>
            <a href="{{ url('/register') }}" class="mt-8 inline-flex rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300">Start Pro</a>
        </article>

        <article class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            <p class="text-xs uppercase tracking-[0.12em] text-slate-400">Business</p>
            <h3 class="mt-2 text-2xl font-semibold text-white">Custom</h3>
            <p class="mt-3 text-xs font-semibold text-cyan-200">Free assisted onboarding included (we can handle DNS).</p>
            <ul class="mt-5 space-y-2 text-sm text-slate-300">
                <li>Higher domain limits (contact sales)</li>
                <li>Advanced traffic insights + anomaly alerts</li>
                <li>Assisted onboarding</li>
                <li>Dedicated support</li>
            </ul>
            <a href="{{ url('/contact') }}" class="mt-8 inline-flex rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300">Contact Sales</a>
        </article>
    </div>

    <p class="mt-6 text-sm text-slate-400">Bandwidth usage is visible in-dashboard with warnings at 80%.</p>
</section>
