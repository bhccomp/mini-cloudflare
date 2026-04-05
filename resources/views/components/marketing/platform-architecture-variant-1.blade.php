<section class="relative w-full border-y border-white/5 bg-[#020817] py-28">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/0 via-white/0 to-white/[0.03]" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-10">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div class="relative order-1 lg:order-1">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_45%,rgba(34,211,238,0.18),transparent_65%)] blur-3xl opacity-55" aria-hidden="true"></div>
                <img
                    src="{{ asset('design-assets/architecture-diagram.png') }}"
                    alt="Platform architecture diagram"
                    class="relative z-10 mx-auto w-full max-w-[1200px]"
                    width="1536"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                >
            </div>

            <div class="order-2 lg:order-2">
                <h2 class="text-3xl font-semibold text-white">How FirePhage Protects You Today</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    Every request passes through FirePhage&apos;s global edge network where our custom WordPress WAF rules automatically filter malicious traffic before it ever touches your origin server.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Edge layer receives and inspects incoming traffic</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>WAF rules filter malicious requests and automated attacks</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Clean traffic is forwarded to your origin servers</span></li>
                    <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Your infrastructure stays protected without complex firewall configuration</span></li>
                </ul>
            </div>
        </div>
    </div>
</section>
