<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="FirePhage Services | WAF, CDN, Cache, DDoS, Bot Protection, WordPress Plugin, Uptime Monitor"
            description="Explore FirePhage services for managed WAF, CDN, cache, DDoS handling, bot protection, WordPress plugin visibility, and uptime monitoring."
            :canonical="route('services.index')"
            :og-url="route('services.index')"
            :structured-data="[
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => 'FirePhage Services',
                    'url' => route('services.index'),
                    'description' => 'Product pages describing FirePhage security, performance, and monitoring services.',
                ],
            ]"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.14),transparent_32%),linear-gradient(180deg,rgba(2,8,23,0.96),rgba(2,6,23,1))]"></div>
            </div>
            <div class="relative z-10">
                <x-marketing.site-header />

                <main class="mx-auto w-full max-w-7xl px-6 pb-20 pt-14 lg:px-8 lg:pb-24 lg:pt-20">
                    <section class="max-w-4xl">
                        <p class="inline-flex rounded-full border border-cyan-400/25 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Services</p>
                        <h1 class="mt-6 text-balance text-4xl font-semibold leading-tight text-white lg:text-6xl">Seven service pages that explain what FirePhage actually does for a website.</h1>
                        <p class="mt-6 max-w-3xl text-lg leading-8 text-slate-300">FirePhage is not just one vague security box. These service pages break the platform down into clear operational layers so buyers understand what problem each part solves and how it fits into protection, performance, and WordPress visibility.</p>
                    </section>

                    <section class="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($services as $slug => $service)
                            <a href="{{ route('services.show', $slug) }}" class="group rounded-3xl border border-white/10 bg-slate-900/75 p-7 transition hover:border-cyan-300/50 hover:bg-slate-900">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">{{ $service['eyebrow'] }}</p>
                                <h2 class="mt-4 text-2xl font-semibold leading-tight text-white">{{ $service['nav_label'] }}</h2>
                                <p class="mt-4 text-sm leading-7 text-slate-300">{{ $service['summary'] }}</p>
                                <div class="mt-6 space-y-3">
                                    @foreach (array_slice($service['outcomes'], 0, 2) as $outcome)
                                        <div class="rounded-2xl border border-white/8 bg-slate-950/60 p-4">
                                            <p class="text-sm font-semibold text-white">{{ $outcome['title'] }}</p>
                                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ $outcome['body'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-6 flex items-center justify-between text-sm font-medium text-cyan-300">
                                    <span>Read service page</span>
                                    <span class="transition group-hover:translate-x-1">→</span>
                                </div>
                            </a>
                        @endforeach
                    </section>
                </main>
                <x-marketing.footer-variant-1 />
            </div>
        </div>
    </body>
</html>
