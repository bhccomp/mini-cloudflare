<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Create Your FirePhage Workspace</title>
        <meta name="description" content="Create your FirePhage workspace and start your free setup flow.">
        <meta name="theme-color" content="#0b1020">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#0a1020] text-slate-100 antialiased">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.18),transparent_28%),radial-gradient(circle_at_80%_15%,rgba(124,58,237,0.18),transparent_24%),linear-gradient(180deg,#08101f_0%,#0b1326_55%,#08111c_100%)]"></div>
            <div class="absolute inset-y-0 left-0 -z-10 w-1/2 bg-[linear-gradient(135deg,rgba(15,23,42,0.95),rgba(15,23,42,0.35))]"></div>

            <div class="mx-auto grid min-h-screen max-w-7xl grid-cols-1 lg:grid-cols-[1.05fr_0.95fr]">
                <section class="flex flex-col justify-between px-6 py-10 sm:px-10 lg:px-12 lg:py-12">
                    <div>
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-sm font-medium text-cyan-200 hover:text-cyan-100">
                            <img src="{{ asset('images/logo-shield-phage-wordmark.svg') }}" alt="FirePhage" class="h-8 w-auto">
                        </a>

                        <div class="mt-16 max-w-2xl">
                            <p class="inline-flex rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-cyan-200">
                                Start Free
                            </p>
                            <h1 class="mt-6 max-w-xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                                Create your workspace and start protecting your first site.
                            </h1>
                            <p class="mt-5 max-w-xl text-base leading-7 text-slate-300">
                                This creates your FirePhage account, your first organization, and gets you into the dashboard so we can wire onboarding, plans, and Stripe on top of a real customer flow.
                            </p>
                        </div>

                        <div class="mt-10 grid max-w-2xl gap-4 sm:grid-cols-3">
                            <article class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">Step 1</p>
                                <p class="mt-2 text-sm text-slate-200">Create your account and workspace owner seat.</p>
                            </article>
                            <article class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">Step 2</p>
                                <p class="mt-2 text-sm text-slate-200">Land inside the dashboard with a ready organization.</p>
                            </article>
                            <article class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">Step 3</p>
                                <p class="mt-2 text-sm text-slate-200">Add plan selection and Stripe sync on the next pass.</p>
                            </article>
                        </div>
                    </div>

                    <div class="mt-12 max-w-2xl text-sm text-slate-400">
                        Already have an account?
                        <a href="{{ url('/app/login') }}" class="font-medium text-cyan-200 hover:text-cyan-100">Sign in</a>
                    </div>
                </section>

                <section class="flex items-center px-6 py-10 sm:px-10 lg:px-12 lg:py-12">
                    <div class="w-full rounded-[2rem] border border-white/10 bg-slate-950/60 p-6 shadow-2xl shadow-cyan-950/20 backdrop-blur sm:p-8">
                        <div class="mb-8">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-200">Workspace Registration</p>
                            <h2 class="mt-3 text-2xl font-semibold text-white">Create your FirePhage account</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-400">Use the details you want to keep for billing, onboarding emails, and your first protected workspace.</p>
                        </div>

                        @if ($errors->any())
                            <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
                                <p class="font-medium">Please fix the highlighted fields.</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
                            @csrf

                            <div>
                                <label for="name" class="mb-2 block text-sm font-medium text-slate-200">Full name</label>
                                <input
                                    id="name"
                                    name="name"
                                    type="text"
                                    value="{{ old('name') }}"
                                    required
                                    autofocus
                                    autocomplete="name"
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                    placeholder="Jane Founder"
                                >
                                @error('name')
                                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="organization_name" class="mb-2 block text-sm font-medium text-slate-200">Organization name</label>
                                <input
                                    id="organization_name"
                                    name="organization_name"
                                    type="text"
                                    value="{{ old('organization_name') }}"
                                    required
                                    autocomplete="organization"
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                    placeholder="Acme Media"
                                >
                                @error('organization_name')
                                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Work email</label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    required
                                    autocomplete="email"
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                    placeholder="jane@acme.com"
                                >
                                @error('email')
                                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="password" class="mb-2 block text-sm font-medium text-slate-200">Password</label>
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        required
                                        autocomplete="new-password"
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                        placeholder="Create a strong password"
                                    >
                                    @error('password')
                                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-200">Confirm password</label>
                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        required
                                        autocomplete="new-password"
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                        placeholder="Repeat password"
                                    >
                                </div>
                            </div>

                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                                Start free
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </body>
</html>
