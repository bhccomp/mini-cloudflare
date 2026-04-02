<div
    data-cookie-consent
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[90] hidden px-4 pb-4 sm:px-6 lg:px-8"
>
    <div class="pointer-events-auto mx-auto max-w-5xl rounded-3xl border border-white/10 bg-slate-950/95 p-5 shadow-[0_24px_80px_rgba(2,8,23,0.55)] backdrop-blur">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold text-white">Cookie preferences</p>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    FirePhage uses necessary cookies for security, sessions, and form protection. Optional cookies can also enable website preferences such as the Crisp support chat widget, analytics, or marketing tools.
                </p>
                <p class="mt-2 text-xs text-slate-400">
                    See the <a href="{{ route('cookies') }}" class="text-cyan-300 hover:text-cyan-200">Cookie Policy</a> and <a href="{{ route('privacy') }}" class="text-cyan-300 hover:text-cyan-200">Privacy Policy</a>.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                <button
                    type="button"
                    data-cookie-consent-manage
                    class="inline-flex items-center justify-center rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-cyan-300/60 hover:text-white"
                >
                    Manage preferences
                </button>
                <button
                    type="button"
                    data-cookie-consent-essential
                    class="inline-flex items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-2.5 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300/60 hover:text-white"
                >
                    Use essential only
                </button>
                <button
                    type="button"
                    data-cookie-consent-accept
                    class="inline-flex items-center justify-center rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
                >
                    Accept all
                </button>
            </div>
        </div>
    </div>
</div>

<div
    data-cookie-consent-modal
    class="fixed inset-0 z-[95] hidden items-end justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm sm:items-center sm:px-6 lg:px-8"
    aria-hidden="true"
>
    <div class="w-full max-w-2xl rounded-[2rem] border border-white/10 bg-slate-950/95 p-6 shadow-[0_28px_90px_rgba(2,8,23,0.6)]">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-white">Cookie preferences</p>
                <p class="mt-2 text-sm leading-6 text-slate-300">Choose which optional cookies FirePhage can use on this site.</p>
            </div>
            <button
                type="button"
                data-cookie-consent-close
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 text-slate-300 transition hover:border-cyan-300/60 hover:text-white"
                aria-label="Close cookie preferences"
            >
                ×
            </button>
        </div>

        <div class="mt-6 space-y-4">
            <label class="flex items-start justify-between gap-4 rounded-2xl border border-white/8 bg-slate-900/70 p-4">
                <div>
                    <p class="text-sm font-semibold text-white">Necessary cookies</p>
                    <p class="mt-1 text-sm leading-6 text-slate-400">Required for security, sessions, CSRF protection, login state, and cookie preference storage.</p>
                </div>
                <input type="checkbox" checked disabled class="mt-1 h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-400">
            </label>

            <label class="flex items-start justify-between gap-4 rounded-2xl border border-white/8 bg-slate-900/70 p-4">
                <div>
                    <p class="text-sm font-semibold text-white">Preference cookies</p>
                    <p class="mt-1 text-sm leading-6 text-slate-400">Used for optional website preferences and support tooling such as the Crisp chat widget when enabled.</p>
                </div>
                <input type="checkbox" data-cookie-consent-category="preferences" class="mt-1 h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-400">
            </label>

            <label class="flex items-start justify-between gap-4 rounded-2xl border border-white/8 bg-slate-900/70 p-4">
                <div>
                    <p class="text-sm font-semibold text-white">Analytics cookies</p>
                    <p class="mt-1 text-sm leading-6 text-slate-400">Used for traffic measurement and product analytics if analytics tools are enabled later.</p>
                </div>
                <input type="checkbox" data-cookie-consent-category="analytics" class="mt-1 h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-400">
            </label>

            <label class="flex items-start justify-between gap-4 rounded-2xl border border-white/8 bg-slate-900/70 p-4">
                <div>
                    <p class="text-sm font-semibold text-white">Marketing cookies</p>
                    <p class="mt-1 text-sm leading-6 text-slate-400">Used for marketing attribution or advertising tools if those services are enabled later.</p>
                </div>
                <input type="checkbox" data-cookie-consent-category="marketing" class="mt-1 h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-400">
            </label>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button
                type="button"
                data-cookie-consent-save-essential
                class="inline-flex items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-2.5 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300/60 hover:text-white"
            >
                Use essential only
            </button>
            <button
                type="button"
                data-cookie-consent-save
                class="inline-flex items-center justify-center rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
            >
                Save preferences
            </button>
        </div>
    </div>
</div>
