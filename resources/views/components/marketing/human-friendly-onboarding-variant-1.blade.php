<section class="relative w-full border-y border-white/5 bg-[#020817] py-28">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-semibold text-white">Human-Friendly Onboarding</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    Our team can handle the entire onboarding process for you.
                    DNS changes, edge deployment, and configuration can be completed by the FirePhage team at no extra cost.
                    No DevOps knowledge required. Our team can assist with DNS cutover and setup.
                </p>

                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>No technical knowledge required</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Our team can perform DNS cutover for you</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>We can handle the DNS setup and onboarding for you.</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Zero configuration on your server</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Ready in minutes</span></li>
                </ul>

                <a href="{{ url('/register') }}" class="mt-8 inline-flex rounded-lg bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-300">
                    Start onboarding
                </a>
            </div>

            <div class="relative flex justify-center lg:justify-end">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_65%_45%,rgba(34,211,238,0.22),transparent_62%)] blur-3xl opacity-65" aria-hidden="true"></div>
                <img
                    src="{{ asset('design-assets/onboarding-team.png') }}"
                    alt="Onboarding support team illustration"
                    class="feature-illustration feature-illustration--onboarding relative z-10 rounded-none"
                    width="1536"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
