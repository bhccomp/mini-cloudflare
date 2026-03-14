<section id="pricing" class="relative w-full border-y border-white/5 bg-[#041427]">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 py-28">
        <div class="mb-8 max-w-3xl">
            <h2 class="text-3xl font-semibold text-white">Simple usage-based pricing — pay only for what you protect</h2>
        </div>

        <div class="grid items-stretch gap-5 lg:grid-cols-3">
            @foreach ($marketingPlans as $plan)
                <x-marketing.pricing-card
                    :plan="$plan->headline ?: $plan->name"
                    :price="$plan->displayPrice()"
                    :suffix="$plan->is_contact_only ? '' : $plan->price_suffix"
                    :cta-href="$plan->is_contact_only ? url('/contact') : (auth()->check() ? (auth()->user()->is_super_admin ? url('/admin') : url('/app')) : url('/register'))"
                    :cta-label="$plan->is_contact_only ? ($plan->cta_label ?: 'Contact Sales') : (auth()->check() ? 'Dashboard' : ($plan->cta_label ?: 'Get Started'))"
                    :featured="$plan->is_featured"
                    :badge="$plan->badge"
                    :description="$plan->description"
                >
                    @foreach ($plan->displayFeatures() as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </x-marketing.pricing-card>
            @endforeach
        </div>

        <p class="mt-6 text-xs text-slate-400">No setup fees • Cancel anytime • Built while we validate and scale</p>
    </div>
</section>
