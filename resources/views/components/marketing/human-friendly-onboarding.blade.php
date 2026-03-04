<section class="py-16 lg:py-20">
    <div class="mx-auto max-w-6xl px-6">
        <div class="grid grid-cols-1 items-center gap-10 md:grid-cols-12">
            <div class="md:col-span-5">
                <h2 class="text-3xl font-semibold text-white">Human-Friendly Onboarding</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    Our team can handle the entire onboarding process for you.
                    DNS changes, edge deployment, and configuration can be completed by the FirePhage team at no extra cost.
                </p>

                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>No technical knowledge required</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Our team can perform DNS cutover for you</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Zero configuration on your server</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Ready in minutes</span></li>
                </ul>

                <a href="{{ url('/register') }}" class="mt-8 inline-flex rounded-lg bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-300">
                    Start onboarding
                </a>
            </div>

            <div class="md:col-span-7">
                <img
                    src="{{ asset('design-assets/onboarding-team.png') }}"
                    alt="Onboarding support team illustration"
                    class="mx-auto w-full max-w-[720px]"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
