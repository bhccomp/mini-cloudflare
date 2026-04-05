<section class="relative w-full border-y border-white/5 bg-[#020817] py-28">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-semibold text-white">Guided Edge Onboarding</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    FirePhage protection starts with DNS, so the cutover has to be handled carefully. We guide the move, stage the edge setup, and keep the transition controlled so the site goes live behind FirePhage without unnecessary downtime risk.
                </p>
                <p class="mt-3 text-xs font-medium text-cyan-200/90">3.2M+ requests filtered last month</p>

                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Safe DNS transition planned around the live site</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Guided setup with staged changes before traffic is fully moved</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Protection is placed in front of WordPress, not forced to live only inside it</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Zero configuration on your server</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>No-downtime path as the target, not an afterthought</span></li>
                </ul>

                <x-marketing.auth-aware-link guest-label="Start 30-day free trial" class="mt-8 inline-flex rounded-lg bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-300" />
            </div>

            <div class="relative flex justify-center lg:justify-end">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_65%_45%,rgba(34,211,238,0.22),transparent_62%)] blur-3xl opacity-65" aria-hidden="true"></div>
                <picture>
                    <source
                        type="image/webp"
                        srcset="{{ asset('design-assets/onboarding-team-960.webp') }} 960w, {{ asset('design-assets/onboarding-team.webp') }} 1536w"
                        sizes="(min-width: 1024px) 637px, 100vw"
                    >
                    <img
                        src="{{ asset('design-assets/onboarding-team.png') }}"
                        alt="Onboarding support team illustration"
                        class="feature-illustration feature-illustration--onboarding relative z-10 rounded-none"
                        width="1536"
                        height="1024"
                        loading="lazy"
                        decoding="async"
                    >
                </picture>
            </div>
        </div>
    </div>
</section>
