<section class="relative w-full border-y border-white/5 bg-[#041427] py-20">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="mx-auto max-w-6xl rounded-2xl border border-slate-700/70 bg-slate-900/65 p-6 sm:p-8">
            <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2 lg:gap-12">
                <div>
                    <h2 class="text-2xl font-semibold text-white sm:text-3xl">Website Availability Monitoring</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-300">
                        FirePhage continuously checks your website from multiple locations and alerts you immediately if your site becomes unreachable or starts responding slowly.
                    </p>
                    <p class="mt-2 text-sm font-medium text-slate-200">Security and availability monitoring in one place.</p>

                    <ul class="mt-6 grid gap-3 text-sm text-slate-200 sm:grid-cols-2">
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Global uptime monitoring</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Response time tracking</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Instant downtime alerts</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Integrated with your security dashboard</span></li>
                    </ul>

                    <div class="mt-7">
                        <p class="text-sm font-semibold text-slate-200">Get notified instantly via:</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (['Slack', 'Email', 'SMS', 'Webhooks'] as $channel)
                                <span class="rounded-full border border-white/10 bg-slate-950/55 px-3 py-1.5 text-xs font-medium text-slate-200">{{ $channel }}</span>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-slate-400">Get notified the moment something goes wrong.</p>
                    </div>
                </div>

                <div class="relative flex justify-center lg:justify-end">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_62%_45%,rgba(34,211,238,0.18),transparent_65%)] blur-3xl opacity-70" aria-hidden="true"></div>
                    <div class="rounded-2xl border border-white/10 bg-slate-950/45 p-2 shadow-[0_18px_42px_rgba(2,8,23,0.4)]">
                        <img
                            src="{{ asset('design-assets/monitor-alerts.png') }}"
                            alt="Availability monitoring and alert integrations preview"
                            class="h-auto w-full max-w-[520px] md:max-w-[560px] xl:max-w-[620px]"
                            width="1536"
                            height="1024"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
