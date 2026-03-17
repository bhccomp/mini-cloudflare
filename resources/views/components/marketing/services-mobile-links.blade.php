@php($services = collect(config('marketing-services', [])))

<div class="mt-1 rounded-xl border border-white/8 bg-slate-950/40 p-2">
    <p class="px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-300">Services</p>
    @foreach ($services as $slug => $service)
        <a href="{{ route('services.show', $slug) }}" class="mt-1 block rounded-lg px-3 py-2 text-sm hover:bg-slate-800/80">
            {{ $service['nav_label'] }}
        </a>
    @endforeach
</div>
