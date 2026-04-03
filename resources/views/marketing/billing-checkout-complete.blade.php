<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="Payment received | FirePhage"
            description="Your FirePhage subscription payment was received."
            :canonical="request()->url()"
            robots="noindex, nofollow"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#0B0C1A] text-white">
        <div class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-20">
            <div class="w-full rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl shadow-cyan-500/10 backdrop-blur">
                <p class="text-sm font-medium uppercase tracking-[0.28em] text-cyan-300">Payment received</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Your FirePhage subscription payment is complete.
                </h1>
                <p class="mt-4 text-base leading-7 text-slate-300">
                    You can now sign in to FirePhage, or let your FirePhage operator continue the onboarding work without repeating checkout.
                </p>
                <div class="mt-8 flex flex-wrap gap-4">
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-full bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
                    >
                        Sign in to FirePhage
                    </a>
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
