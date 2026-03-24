@php
    $trialPlan = $marketingTrialPlan ?? null;
    $heroGuestLabel = $trialPlan
        ? 'Start '.$trialPlan->trialLabel()
        : 'Start Protecting Your Site';
    $heroTrialCopy = $trialPlan
        ? $trialPlan->name.' includes a '.$trialPlan->trialLabel().'. Checkout still collects a payment method, and billing starts only if the trial is not canceled.'
        : null;
@endphp

<section class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
        <img
            src="{{ asset('images/hero-banner-new.png') }}"
            alt=""
            class="absolute inset-0 h-full w-full object-cover object-right opacity-[0.85]"
            loading="eager"
            decoding="async"
            aria-hidden="true"
        >
        <div
            class="absolute inset-0"
            style="background: linear-gradient(90deg, rgba(2,8,23,0.95) 0%, rgba(2,8,23,0.85) 35%, rgba(2,8,23,0.4) 65%, rgba(2,8,23,0.1) 100%);"
        ></div>
    </div>

    <div class="relative z-10 mx-auto w-full max-w-7xl px-6 pb-32 pt-40 lg:px-8 lg:pb-32 lg:pt-52">
        <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-2 lg:gap-12">
            <div class="max-w-3xl pt-8">
                    <p class="mb-4 inline-flex rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">{{ $trialPlan ? $trialPlan->trialLabel().' on '.$trialPlan->name : 'Security Platform' }}</p>
                    <h1 class="mb-5 text-balance text-4xl font-semibold leading-[1.06] text-white sm:text-5xl lg:text-6xl">Shield Your Origin. Control Traffic at the Edge.</h1>
                    <p class="text-lg leading-8 text-slate-200/85">Edge firewall + origin protection that blocks attacks before they hit your server.</p>

                    <ul class="mt-6 space-y-2 text-sm text-slate-200/90">
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Human-readable dashboard (no security jargon)</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>We can handle DNS cutover for you, at no extra cost</span></li>
                        <li class="flex items-start gap-2"><span class="text-cyan-300">•</span><span>Stops bots, brute force, and noisy traffic fast</span></li>
                    </ul>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-start">
                        <x-marketing.auth-aware-link :guest-label="$heroGuestLabel" class="w-full rounded-xl bg-cyan-400 px-6 py-3 text-center text-sm font-semibold text-slate-950 shadow-[0_0_0_1px_rgba(34,211,238,0.24),0_0_22px_rgba(34,211,238,0.22)] hover:bg-cyan-300 sm:w-auto" />
                        <a href="#dashboard-preview" class="w-full rounded-xl border border-slate-700 px-6 py-3 text-center text-sm font-semibold text-slate-100 hover:border-slate-500 sm:w-auto">View Dashboard Demo</a>
                    </div>
                    @if ($heroTrialCopy)
                        <p class="mt-3 text-sm text-slate-300/90">{{ $heroTrialCopy }}</p>
                    @endif
            </div>

            <div class="relative flex justify-center lg:justify-end">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_60%_45%,rgba(124,58,237,0.22),transparent_62%)] blur-3xl opacity-70" aria-hidden="true"></div>
                <img
                    src="{{ asset('design-assets/dashboard-laptop.png') }}"
                    alt="FirePhage dashboard preview on laptop"
                    class="hero-visual-laptop relative z-10"
                    width="1536"
                    height="1024"
                    loading="eager"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
