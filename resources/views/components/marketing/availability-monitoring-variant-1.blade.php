<section class="relative w-full border-y border-white/5 bg-[#041427] py-28">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-semibold text-white">Website Availability Monitoring</h2>
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
                    <p class="text-sm font-semibold text-slate-200">Alerts delivered via</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['Slack', 'Email', 'SMS', 'Webhooks'] as $channel)
                            <span class="rounded-full border border-white/10 bg-slate-900/70 px-3 py-1.5 text-xs font-medium text-slate-200">{{ $channel }}</span>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-slate-400">Get notified the moment something goes wrong.</p>
                </div>
            </div>

            <div class="relative flex justify-center lg:justify-end">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_62%_45%,rgba(34,211,238,0.18),transparent_65%)] blur-3xl opacity-70" aria-hidden="true"></div>
                <img
                    src="{{ asset('design-assets/monitor-alerts.png') }}"
                    alt="Availability monitoring and alert integrations preview"
                    class="relative z-10 h-auto w-full max-w-[520px] md:max-w-[560px] xl:max-w-[620px] drop-shadow-[0_16px_34px_rgba(2,8,23,0.36)]"
                    width="1536"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
