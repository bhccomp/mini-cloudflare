<section id="pricing" class="relative w-full border-y border-white/5 bg-[#041427]">
    @php
        $pricingCtaHref = auth()->check()
            ? (auth()->user()->is_super_admin ? url('/admin') : url('/app'))
            : url('/register');
    @endphp
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 py-28">
        <div class="mb-8 max-w-2xl">
            <h2 class="text-3xl font-semibold text-white">Pricing</h2>
            <p class="mt-3 text-sm text-slate-300">Choose the plan that matches your traffic and support needs.</p>
        </div>

        <div class="grid items-stretch gap-5 lg:grid-cols-3">
            <x-marketing.pricing-card
                plan="Simple"
                price="$29"
                :cta-href="$pricingCtaHref"
                :cta-label="auth()->check() ? 'Dashboard' : 'Get Started'"
            >
                <li>1 domain</li>
                <li>Basic WAF (Firewall rules) settings</li>
                <li>Monitoring every 5 minutes</li>
                <li>Standard analytics</li>
            </x-marketing.pricing-card>

            <x-marketing.pricing-card
                plan="Pro"
                price="$99"
                :cta-href="$pricingCtaHref"
                :cta-label="auth()->check() ? 'Dashboard' : 'Start Pro'"
                :featured="true"
            >
                <li>Up to 10 domains</li>
                <li>Advanced WAF (Firewall rules) settings</li>
                <li>Monitoring every 1 minute</li>
                <li>Origin IP protection + health signals</li>
                <li>Priority alerts</li>
            </x-marketing.pricing-card>

            <x-marketing.pricing-card
                plan="Custom"
                price="Custom"
                suffix=""
                cta-href="{{ url('/contact') }}"
                cta-label="Contact Sales"
            >
                <li>Higher domain limits (contact sales)</li>
                <li>Advanced traffic insights + anomaly alerts</li>
                <li>Dedicated support</li>
                <li>Team onboarding and migration guidance</li>
            </x-marketing.pricing-card>
        </div>
    </div>
</section>
