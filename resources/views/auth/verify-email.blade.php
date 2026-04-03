<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verify Your Email | FirePhage</title>
        <meta name="description" content="Verify your email address to continue using FirePhage.">
        <meta name="theme-color" content="#0b1020">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#0a1020] text-slate-100 antialiased">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.18),transparent_28%),radial-gradient(circle_at_80%_15%,rgba(124,58,237,0.18),transparent_24%),linear-gradient(180deg,#08101f_0%,#0b1326_55%,#08111c_100%)]"></div>

            <div class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-10 sm:px-10">
                <div class="w-full rounded-[2rem] border border-white/10 bg-slate-950/60 p-6 shadow-2xl shadow-cyan-950/20 backdrop-blur sm:p-8">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-200">Verify Email</p>
                    <h1 class="mt-3 text-3xl font-semibold text-white">Check your inbox before continuing.</h1>
                    <p class="mt-4 text-sm leading-7 text-slate-300">
                        FirePhage sent a verification email to <span class="font-medium text-white">{{ auth()->user()?->email }}</span>.
                        Account access stays limited until this email is verified. Confirm it first, then you can add sites, review organizations, and manage protection settings.
                    </p>

                    @if (session('status'))
                        <div class="mt-6 rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-4 text-sm text-cyan-100">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="mt-8 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                                Resend verification email
                            </button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/5">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
