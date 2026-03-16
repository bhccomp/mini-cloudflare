<section id="pricing" class="relative w-full border-y border-white/5 bg-[#041427]">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 py-28">
        <div class="mb-8 max-w-2xl">
            <h2 class="text-3xl font-semibold text-white">Pricing</h2>
            <p class="mt-3 text-sm text-slate-300">Choose the plan that matches your traffic and support needs.</p>
        </div>

        <div class="grid items-stretch gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($marketingPlans as $plan)
                <x-marketing.pricing-card
                    :plan="$plan->headline ?: $plan->name"
                    :price="$plan->displayPrice()"
                    :suffix="$plan->is_contact_only ? '' : $plan->price_suffix"
                    :features="$plan->displayFeatures()"
                    :limits="$plan->displayLimits()"
                    :cta-href="$plan->is_contact_only ? url('/contact') : (auth()->check() ? (auth()->user()->is_super_admin ? url('/admin') : \App\Filament\App\Resources\SiteResource::getUrl('create')) : url('/register'))"
                    :cta-label="$plan->is_contact_only ? ($plan->cta_label ?: 'Contact Sales') : ($plan->cta_label ?: 'Get Started')"
                    :featured="$plan->is_featured"
                    :badge="$plan->badge"
                    :description="$plan->description"
                />
            @endforeach
        </div>
    </div>
</section>
