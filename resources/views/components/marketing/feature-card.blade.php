@props([
    'icon' => 'shield',
    'title',
    'description',
])

<article class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 transition duration-200 hover:-translate-y-1 hover:border-cyan-300/45">
    <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-500/10 text-cyan-300">
        @switch($icon)
            @case('traffic')
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 8h16M4 16h16M8 4v16M16 4v16"/>
                </svg>
                @break
            @case('origin')
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/>
                </svg>
                @break
            @case('monitoring')
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M3 12h4l2-4 4 8 2-4h6"/>
                </svg>
                @break
            @case('alerts')
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 4a5 5 0 0 0-5 5v2c0 1.7-.7 3.4-2 4.6h14c-1.3-1.2-2-2.9-2-4.6V9a5 5 0 0 0-5-5z"/>
                    <path d="M10 19a2 2 0 0 0 4 0"/>
                </svg>
                @break
            @default
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/>
                </svg>
        @endswitch
    </div>
    <h3 class="mt-4 text-base font-semibold text-white">{{ $title }}</h3>
    <p class="mt-2 text-sm leading-6 text-slate-300">{{ $description }}</p>
</article>
