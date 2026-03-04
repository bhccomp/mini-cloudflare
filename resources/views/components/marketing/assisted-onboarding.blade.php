<x-marketing.section class="border-y border-slate-800/80 bg-slate-900/40">
    <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
        <div>
                <h2 class="text-3xl font-semibold text-white">Free Assisted Onboarding (We&apos;ll handle DNS for you)</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">
                    If you don&apos;t want to touch DNS records, our team can do the cutover for you at no extra cost.
                    We verify everything and keep downtime risk low.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    @foreach ([
                        'We update DNS for you',
                        'We verify cutover',
                        'No technical knowledge required',
                        'No extra fees',
                        'You can still do it yourself if you prefer',
                    ] as $item)
                        <li class="flex items-start gap-2">
                            <svg viewBox="0 0 20 20" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-300" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.2 7.2a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.42l2.293 2.294 6.493-6.494a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="#pricing" class="mt-7 inline-flex rounded-lg border border-cyan-300/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 hover:border-cyan-200/60 hover:text-cyan-100">
                    Request assisted onboarding
                </a>
        </div>

        <div class="relative flex items-center justify-center lg:justify-end">
            <div class="pointer-events-none absolute inset-0 rounded-[32px] bg-[radial-gradient(circle_at_70%_40%,rgba(34,211,238,0.18),transparent_55%),radial-gradient(circle_at_20%_80%,rgba(59,130,246,0.15),transparent_55%)]" aria-hidden="true"></div>

            <div class="relative w-full max-w-[560px]">
                <div class="max-h-[420px] overflow-hidden rounded-2xl border border-white/10 bg-slate-950/55 p-3 shadow-[0_20px_60px_rgba(2,8,23,0.45)]">
                    <img src="{{ asset('images/onboarding-illustration.svg') }}" alt="Assisted onboarding illustration" class="h-auto w-full rounded-xl" loading="lazy" decoding="async">
                </div>
                <div class="absolute -bottom-4 right-4 rounded-full border border-emerald-300/40 bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-200 shadow-[0_8px_20px_rgba(0,0,0,0.25)]">
                    Verified cutover
                </div>
            </div>
        </div>
    </div>
</x-marketing.section>
