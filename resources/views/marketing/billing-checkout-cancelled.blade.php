<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="Checkout cancelled | FirePhage"
            description="Your FirePhage checkout was cancelled."
            :canonical="request()->url()"
            robots="noindex, nofollow"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
    </head>
    <body class="min-h-screen bg-[#0B0C1A] text-white">
        <div class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-20">
            <div class="w-full rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl shadow-cyan-500/10 backdrop-blur">
                <p class="text-sm font-medium uppercase tracking-[0.28em] text-amber-300">Checkout cancelled</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    No payment was completed.
                </h1>
                <p class="mt-4 text-base leading-7 text-slate-300">
                    You can close this page and use the same FirePhage payment link again later, or contact your FirePhage operator if you need a fresh one.
                </p>
                <div class="mt-8">
                    <a
                        href="{{ route('home') }}"
                        class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/5"
                    >
                        Back to homepage
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>
