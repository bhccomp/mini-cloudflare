@props([
    'title',
    'description',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            :title="$title"
            :description="$description"
            :canonical="request()->url()"
            :structured-data="[
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $title,
                    'url' => request()->url(),
                    'description' => $description,
                ],
            ]"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.14),transparent_36%),linear-gradient(180deg,rgba(2,8,23,0.92),rgba(2,6,23,1))]"></div>
            </div>

            <x-marketing.site-header />

            <main class="relative mx-auto w-full max-w-5xl px-6 py-16 lg:px-8 lg:py-20">
                <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-8 shadow-[0_32px_90px_rgba(2,8,23,0.55)] backdrop-blur xl:p-12">
                    <div class="mb-10 flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-8">
                        <div>
                            <a href="{{ route('home') }}" class="text-sm text-cyan-300 hover:text-cyan-200">&larr; Back to FirePhage</a>
                            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-white lg:text-5xl">{{ $title }}</h1>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">{{ $description }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-300">
                            <a href="{{ route('terms') }}" class="rounded-full border border-white/10 px-4 py-2 hover:border-cyan-300/60 hover:text-white">Terms</a>
                            <a href="{{ route('privacy') }}" class="rounded-full border border-white/10 px-4 py-2 hover:border-cyan-300/60 hover:text-white">Privacy</a>
                            <a href="{{ route('refund-policy') }}" class="rounded-full border border-white/10 px-4 py-2 hover:border-cyan-300/60 hover:text-white">Refunds</a>
                            <a href="{{ route('acceptable-use') }}" class="rounded-full border border-white/10 px-4 py-2 hover:border-cyan-300/60 hover:text-white">Acceptable Use</a>
                            <a href="{{ route('contact') }}" class="rounded-full border border-white/10 px-4 py-2 hover:border-cyan-300/60 hover:text-white">Contact</a>
                        </div>
                    </div>

                    <div class="prose prose-invert max-w-none prose-headings:text-white prose-p:text-slate-300 prose-li:text-slate-300 prose-strong:text-white prose-a:text-cyan-300">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
