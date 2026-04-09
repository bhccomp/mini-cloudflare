<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="Contact FirePhage | Sales, onboarding, and product help"
            description="Talk to FirePhage about onboarding, plans, migration help, WordPress protection, or a specific site that needs better firewall coverage."
            :canonical="route('contact')"
            :og-url="route('contact')"
            :structured-data="[
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'ContactPage',
                    'name' => 'Contact FirePhage',
                    'url' => route('contact'),
                    'description' => 'Reach FirePhage for onboarding, billing questions, migration planning, or security/product help.',
                ],
            ]"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
        @if ($usesTurnstile)
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endif
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.16),transparent_34%),linear-gradient(180deg,rgba(2,8,23,0.96),rgba(2,6,23,1))]"></div>
                <div class="absolute left-1/2 top-28 h-80 w-80 -translate-x-1/2 rounded-full bg-cyan-500/10 blur-3xl"></div>
            </div>

            <div class="relative z-10">
                <x-marketing.site-header />

                <main class="mx-auto w-full max-w-7xl px-6 pb-20 pt-10 lg:px-8 lg:pb-24 lg:pt-16">
                    <section class="grid gap-10 lg:grid-cols-[0.92fr_1.08fr] lg:items-start">
                        <div class="max-w-3xl">
                            <p class="inline-flex rounded-full border border-cyan-400/25 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Contact FirePhage</p>
                            <h1 class="mt-6 text-balance text-4xl font-semibold leading-tight text-white lg:text-6xl">Ask about onboarding, billing, migration, or a specific website that needs better protection.</h1>
                            <p class="mt-6 text-lg leading-8 text-slate-300">Use this form if you are evaluating FirePhage, need help planning a move, want to talk through pricing, or need a real answer before you commit a site. We keep the process human and practical.</p>

                            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                                <article class="rounded-3xl border border-white/10 bg-slate-900/75 p-6">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">Best for</p>
                                    <ul class="mt-4 space-y-3 text-sm leading-7 text-slate-300">
                                        <li>Plan and pricing questions</li>
                                        <li>Onboarding or DNS migration help</li>
                                        <li>WordPress and WooCommerce protection fit</li>
                                        <li>Agency or multi-site evaluation</li>
                                    </ul>
                                </article>
                                <article class="rounded-3xl border border-white/10 bg-slate-900/75 p-6">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">Direct email</p>
                                    <p class="mt-4 text-xl font-semibold text-white">
                                        <a href="mailto:nikola@firephage.com" class="hover:text-cyan-200">nikola@firephage.com</a>
                                    </p>
                                    <p class="mt-4 text-sm leading-7 text-slate-300">If you are already a customer, signing in and using the support area is usually the fastest path.</p>
                                    @auth
                                        @if (! auth()->user()->is_super_admin)
                                            <a href="{{ url('/app/support') }}" class="mt-5 inline-flex items-center rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 hover:border-cyan-300/60 hover:text-white">
                                                Open support tickets
                                            </a>
                                        @endif
                                    @endauth
                                </article>
                            </div>

                            <div class="mt-8 rounded-[1.75rem] border border-cyan-400/20 bg-cyan-500/8 p-6 shadow-[0_20px_60px_rgba(2,8,23,0.28)]">
                                <p class="text-sm font-semibold text-white">What happens next</p>
                                <p class="mt-3 text-sm leading-7 text-slate-200">Tell us what you are trying to protect, what is blocked right now, or what kind of migration you are planning. We will reply with the shortest useful answer we can, not a generic sales sequence.</p>
                            </div>
                        </div>

                        <section class="rounded-[2rem] border border-white/10 bg-slate-900/82 p-6 shadow-[0_26px_80px_rgba(2,8,23,0.52)] lg:p-8">
                            <div class="rounded-[1.5rem] border border-white/8 bg-slate-950/55 p-5 lg:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">Send a message</p>
                                        <h2 class="mt-3 text-2xl font-semibold text-white">Tell us what you need help with.</h2>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-slate-900/70 px-4 py-3 text-right">
                                        <p class="text-xs uppercase tracking-[0.14em] text-slate-400">Typical topics</p>
                                        <p class="mt-2 text-sm font-medium text-white">Sales, onboarding, billing, migration</p>
                                    </div>
                                </div>

                                @if (session('contact_success'))
                                    <div class="mt-6 rounded-2xl border border-emerald-400/25 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                                        {{ session('contact_success') }}
                                    </div>
                                @endif

                                @if ($errors->any())
                                    <div class="mt-6 rounded-2xl border border-rose-400/25 bg-rose-500/10 px-5 py-4 text-sm text-rose-100">
                                        {{ $errors->first() }}
                                    </div>
                                @endif

                                <form action="{{ route('contact.store') }}" method="POST" class="mt-6 space-y-5">
                                    @csrf
                                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                                    <input type="hidden" name="submitted_from" value="{{ now()->getTimestampMs() }}">

                                    <div class="grid gap-5 md:grid-cols-2">
                                        <label class="block">
                                            <span class="mb-2 block text-sm font-medium text-slate-200">Name</span>
                                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                                        </label>
                                        <label class="block">
                                            <span class="mb-2 block text-sm font-medium text-slate-200">Email</span>
                                            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                                        </label>
                                        <label class="block">
                                            <span class="mb-2 block text-sm font-medium text-slate-200">Company</span>
                                            <input type="text" name="company_name" value="{{ old('company_name') }}" class="w-full rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                                        </label>
                                        <label class="block">
                                            <span class="mb-2 block text-sm font-medium text-slate-200">Website</span>
                                            <input type="url" name="website_url" value="{{ old('website_url') }}" placeholder="https://example.com" class="w-full rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                                        </label>
                                    </div>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-slate-200">Topic</span>
                                        <select name="topic" required class="w-full rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-white focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                                            @php($topic = old('topic'))
                                            <option value="">Choose a topic</option>
                                            <option value="Sales & Plans" @selected($topic === 'Sales & Plans')>Sales & Plans</option>
                                            <option value="Onboarding & Migration" @selected($topic === 'Onboarding & Migration')>Onboarding & Migration</option>
                                            <option value="WordPress / Plugin" @selected($topic === 'WordPress / Plugin')>WordPress / Plugin</option>
                                            <option value="Billing" @selected($topic === 'Billing')>Billing</option>
                                            <option value="General Question" @selected($topic === 'General Question')>General Question</option>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-slate-200">Message</span>
                                        <textarea name="message" rows="7" required class="w-full rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">{{ old('message') }}</textarea>
                                        <span class="mt-2 block text-xs text-slate-400">Share enough context for us to answer properly. Site URL, expected traffic, migration timeline, or current blocker all help.</span>
                                    </label>

                                    @if ($usesTurnstile && $turnstileSiteKey)
                                        <div class="pt-1">
                                            <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="dark"></div>
                                        </div>
                                    @else
                                        <p class="text-xs leading-6 text-slate-400">Basic anti-spam checks are active. If you enable Cloudflare Turnstile keys later, this form will automatically switch to Turnstile verification.</p>
                                    @endif

                                    <div class="flex flex-col gap-3 border-t border-white/8 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="max-w-xl text-xs leading-6 text-slate-400">By sending this message, you agree that FirePhage can use your details to respond and follow up about your request. For customer account issues, the signed-in support area is preferred.</p>
                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">
                                            Send message
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    </section>
                </main>

                <x-marketing.footer-variant-1 />
            </div>
        </div>
    </body>
</html>
