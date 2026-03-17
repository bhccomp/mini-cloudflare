@php($services = collect(config('marketing-services', [])))

<div class="relative z-[120]" data-services-dropdown>
    <button
        type="button"
        class="inline-flex items-center gap-2 text-sm text-slate-300 transition hover:text-white"
        aria-expanded="false"
        aria-haspopup="true"
        data-services-dropdown-toggle
    >
        <span>Services</span>
        <svg viewBox="0 0 20 20" class="h-4 w-4 transition duration-150" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" data-services-dropdown-icon>
            <path d="m5 7.5 5 5 5-5" />
        </svg>
    </button>

    <div class="absolute left-0 top-full z-[140] mt-3 hidden w-80" data-services-dropdown-panel>
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/98 p-2 shadow-[0_20px_60px_rgba(2,8,23,0.55)] backdrop-blur">
            <div class="rounded-xl border border-white/8 bg-slate-900/75 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-300">Services</p>
                <p class="mt-2 text-sm leading-6 text-slate-400">Explore each FirePhage capability in a dedicated product page.</p>
            </div>

            <div class="mt-2 space-y-1">
                @foreach ($services as $slug => $service)
                    <a href="{{ route('services.show', $slug) }}" class="flex items-center justify-between rounded-xl px-4 py-3 text-sm text-slate-200 transition hover:bg-slate-900 hover:text-white">
                        <div>
                            <p class="font-medium text-white">{{ $service['nav_label'] }}</p>
                            <p class="mt-1 text-xs leading-5 text-slate-400">{{ $service['summary'] }}</p>
                        </div>
                        <span class="ml-4 text-cyan-300">→</span>
                    </a>
                @endforeach
            </div>

        </div>
    </div>
</div>
