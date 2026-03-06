<section id="pricing" class="relative w-full border-y border-white/5 bg-[#041427]">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 py-28">
        <div class="mb-8 max-w-3xl">
            <h2 class="text-3xl font-semibold text-white">Simple usage-based pricing — pay only for what you protect</h2>
        </div>

        <div class="grid items-stretch gap-5 lg:grid-cols-3">
            <article class="relative flex h-full flex-col rounded-2xl border border-slate-700/80 bg-slate-900/65 p-6">
                <p class="text-xs uppercase tracking-[0.12em] text-slate-400">Starter</p>
                <h3 class="mt-2 text-2xl font-semibold text-white">$29 <span class="text-sm text-slate-400">/ month</span></h3>
                <ul class="mt-5 space-y-2 text-sm text-slate-300">
                    <li>Up to 500,000 requests/month</li>
                    <li>Basic edge filtering</li>
                    <li>Email alerts</li>
                    <li>Standard support</li>
                </ul>
                <a href="{{ url('/register') }}" class="mt-auto w-full rounded-lg bg-cyan-500 py-3 text-center text-sm font-medium text-slate-950 hover:bg-cyan-400">Get Started</a>
            </article>

            <article class="relative flex h-full scale-105 flex-col rounded-2xl border border-cyan-400 bg-slate-900/70 p-6 shadow-lg">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full border border-cyan-300/40 bg-cyan-400 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-950">
                    Most Popular
                </div>
                <p class="text-xs uppercase tracking-[0.12em] text-cyan-300">Growth</p>
                <h3 class="mt-2 text-2xl font-semibold text-white">$99 <span class="text-sm text-slate-400">/ month</span></h3>
                <ul class="mt-5 space-y-2 text-sm text-slate-300">
                    <li>Up to 5 million requests/month</li>
                    <li>Advanced attack patterns + origin shielding</li>
                    <li>Slack + SMS + Email + Webhook alerts</li>
                    <li>Priority support</li>
                    <li>Overage: $0.02 per 1,000 extra requests</li>
                </ul>
                <a href="{{ url('/register') }}" class="mt-auto w-full rounded-lg bg-cyan-500 py-3 text-center text-sm font-medium text-slate-950 hover:bg-cyan-400">Start Growth</a>
            </article>

            <article class="relative flex h-full flex-col rounded-2xl border border-slate-700/80 bg-slate-900/65 p-6">
                <p class="text-xs uppercase tracking-[0.12em] text-slate-400">Enterprise</p>
                <h3 class="mt-2 text-2xl font-semibold text-white">Custom</h3>
                <ul class="mt-5 space-y-2 text-sm text-slate-300">
                    <li>10+ million requests/month</li>
                    <li>Dedicated edge configuration</li>
                    <li>Custom WordPress security rules</li>
                    <li>SLA + personal onboarding</li>
                    <li>Path to your own infrastructure when ready</li>
                </ul>
                <a href="{{ url('/contact') }}" class="mt-auto w-full rounded-lg bg-cyan-500 py-3 text-center text-sm font-medium text-slate-950 hover:bg-cyan-400">Contact Sales</a>
            </article>
        </div>

        <p class="mt-6 text-xs text-slate-400">No setup fees • Cancel anytime • Built while we validate and scale</p>
    </div>
</section>
