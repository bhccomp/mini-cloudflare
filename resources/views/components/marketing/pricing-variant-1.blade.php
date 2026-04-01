<section id="pricing" class="relative w-full border-y border-white/5 bg-[#041427]">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/[0.01] to-slate-950/40" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 py-28">
        <div class="mb-8 max-w-3xl">
            <h2 class="text-3xl font-semibold text-white">Simple usage-based pricing with a free trial for the real platform</h2>
            <p class="mt-4 text-sm leading-7 text-slate-300">Start with the full edge product on trial, or use the lighter WordPress entry path first if you are not ready for DNS onboarding yet.</p>
            <p class="mt-3 text-sm font-medium text-slate-200">30-day free trial includes full protection for one site.</p>
        </div>

        <div class="grid items-stretch gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($marketingPlans as $plan)
                @php
                    $isStarterPlan = strtolower((string) $plan->code) === 'starter';
                    $ctaLabel = $plan->is_contact_only
                        ? ($plan->cta_label ?: 'Contact Sales')
                        : ($isStarterPlan ? 'Start 30-day free trial' : ($plan->cta_label ?: 'Get Started'));

                    $description = $plan->description;

                    if ($isStarterPlan && (int) $plan->trial_days > 0) {
                        $description = 'Start here with a 30-day free trial';
                    }
                @endphp
                <x-marketing.pricing-card
                    :plan="$plan->headline ?: $plan->name"
                    :price="$plan->displayPrice()"
                    :suffix="$plan->is_contact_only ? '' : $plan->price_suffix"
                    :features="$plan->displayFeatures()"
                    :limits="$plan->displayLimits()"
                    :trial-days="$plan->trial_days"
                    :cta-href="$plan->is_contact_only ? url('/contact') : (auth()->check() ? (auth()->user()->is_super_admin ? url('/admin') : \App\Filament\App\Resources\SiteResource::getUrl('create')) : url('/register'))"
                    :cta-label="$ctaLabel"
                    :featured="$plan->is_featured"
                    :badge="$plan->badge"
                    :description="$description"
                />
            @endforeach
        </div>

        <p class="mt-6 text-xs text-slate-400">Free trial available • Full protection for one site • No setup fees • Cancel anytime • Plugin visibility and checksum tooling can still be used as a lighter entry path</p>
    </div>
</section>
