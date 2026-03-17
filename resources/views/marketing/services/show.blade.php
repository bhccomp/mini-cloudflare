<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ 'FirePhage ' . $service['nav_label'] . ' | ' . $service['summary'] }}</title>
        <meta name="description" content="{{ $service['description'] }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ 'FirePhage ' . $service['nav_label'] }}">
        <meta property="og:description" content="{{ $service['description'] }}">
        <meta property="og:url" content="{{ route('services.show', $serviceKey) }}">
        <meta property="og:site_name" content="FirePhage">
        <meta name="theme-color" content="#030712">
        <link rel="canonical" href="{{ route('services.show', $serviceKey) }}">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .service-graph-bar { background: linear-gradient(180deg, rgba(34,211,238,0.95), rgba(59,130,246,0.5)); }
        </style>
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.14),transparent_32%),linear-gradient(180deg,rgba(2,8,23,0.96),rgba(2,6,23,1))]"></div>
            </div>
            <div class="relative z-10">
                <x-marketing.site-header />

                <main class="mx-auto w-full max-w-7xl px-6 pb-20 pt-10 lg:px-8 lg:pb-24 lg:pt-16">
                    <section class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                        <div class="max-w-3xl">
                            <a href="{{ route('services.index') }}" class="text-sm text-cyan-300 hover:text-cyan-200">&larr; All services</a>
                            <p class="mt-6 inline-flex rounded-full border border-cyan-400/25 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">{{ $service['eyebrow'] }}</p>
                            <h1 class="mt-6 text-balance text-4xl font-semibold leading-tight text-white lg:text-6xl">{{ $service['title'] }}</h1>
                            <p class="mt-6 text-lg leading-8 text-slate-300">{{ $service['description'] }}</p>

                            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                                @foreach ($service['proof'] as $metric)
                                    <div class="rounded-2xl border border-white/10 bg-slate-900/75 p-5">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">{{ $metric['label'] }}</p>
                                        <p class="mt-3 text-2xl font-semibold text-white">{{ $metric['value'] }}</p>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-8 flex flex-wrap items-center gap-3">
                                <x-marketing.auth-aware-link guest-label="Start Free" class="rounded-xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-400" />
                                <a href="{{ route('home') }}#pricing" class="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-100 hover:border-cyan-300/60 hover:text-white">See pricing</a>
                            </div>
                        </div>

                        <div class="rounded-[2rem] border border-white/10 bg-slate-900/80 p-6 shadow-[0_26px_80px_rgba(2,8,23,0.52)]">
                            <div class="overflow-hidden rounded-[1.5rem] border border-white/10 bg-slate-950/60 p-4">
                                <img
                                    src="{{ asset('images/' . $service['image']) }}"
                                    alt="{{ $service['image_alt'] }}"
                                    class="h-full w-full rounded-[1rem] object-cover"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </div>
                            <div class="mt-5 rounded-2xl border border-white/8 bg-slate-950/60 p-5">
                                <p class="text-sm font-semibold text-white">{{ $service['graph']['title'] }}</p>
                                <div class="mt-5 flex h-32 items-end gap-2">
                                    @foreach ($service['graph']['points'] as $point)
                                        <div class="service-graph-bar w-full rounded-t-xl" style="height: {{ max(12, min(100, $point)) }}%;"></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="mt-16 grid gap-6 lg:grid-cols-3">
                        @foreach ($service['outcomes'] as $outcome)
                            <article class="rounded-3xl border border-white/10 bg-slate-900/75 p-7">
                                <p class="text-lg font-semibold text-white">{{ $outcome['title'] }}</p>
                                <p class="mt-4 text-sm leading-7 text-slate-300">{{ $outcome['body'] }}</p>
                            </article>
                        @endforeach
                    </section>

                    <section class="mt-16 grid gap-10 lg:grid-cols-[0.95fr_1.05fr]">
                        <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-8">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">Problem this solves</p>
                            <h2 class="mt-4 text-3xl font-semibold text-white">Why teams end up needing {{ $service['nav_label'] }}</h2>
                            <p class="mt-5 text-base leading-8 text-slate-300">{{ $service['problem'] }}</p>

                            <div class="mt-8 rounded-2xl border border-cyan-400/15 bg-cyan-500/5 p-6">
                                <p class="text-sm font-semibold text-white">{{ $service['example']['title'] }}</p>
                                <p class="mt-3 text-sm leading-7 text-slate-300">{{ $service['example']['body'] }}</p>
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            @foreach ($service['feature_cards'] as $card)
                                <article class="rounded-3xl border border-white/10 bg-slate-900/75 p-7">
                                    <p class="text-lg font-semibold text-white">{{ $card['title'] }}</p>
                                    <p class="mt-4 text-sm leading-7 text-slate-300">{{ $card['body'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <section class="mt-16 rounded-[2rem] border border-white/10 bg-slate-900/80 p-8 lg:p-10">
                        <div class="grid gap-8 lg:grid-cols-[1fr_0.95fr] lg:items-center">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">How it fits FirePhage</p>
                                <h2 class="mt-4 text-3xl font-semibold text-white">A service page that connects back to the real product, not a thin SEO shell.</h2>
                                <p class="mt-5 text-base leading-8 text-slate-300">Every FirePhage service page is meant to explain one capability clearly, then connect that capability back to pricing, onboarding, the dashboard, and the real website problems teams are trying to solve.</p>
                            </div>

                            <div class="rounded-3xl border border-white/8 bg-slate-950/60 p-6">
                                <p class="text-sm font-semibold text-white">Related services</p>
                                <div class="mt-5 space-y-3">
                                    @foreach ($services->except($serviceKey)->take(4) as $slug => $related)
                                        <a href="{{ route('services.show', $slug) }}" class="flex items-center justify-between rounded-2xl border border-white/8 bg-slate-900/70 px-4 py-3 text-sm text-slate-200 hover:border-cyan-300/50 hover:text-white">
                                            <span>{{ $related['nav_label'] }}</span>
                                            <span class="text-cyan-300">→</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <x-marketing.footer-variant-1 />
            </div>
        </div>
    </body>
</html>
