<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>FirePhage | Early Access</title>
        <meta name="description" content="Join the FirePhage early access list for launch pricing, guided onboarding, and first access to managed edge protection built for real websites.">
        <meta property="og:type" content="website">
        <meta property="og:title" content="FirePhage | Early Access">
        <meta property="og:description" content="Reserve your place for launch pricing and guided onboarding.">
        <meta property="og:url" content="{{ route('early-access') }}">
        <meta property="og:site_name" content="FirePhage">
        <meta name="theme-color" content="#030712">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .early-theme {
                --fp-bg: #0B0C1A;
                --fp-surface: rgba(18, 21, 40, 0.82);
                --fp-surface-soft: rgba(255, 255, 255, 0.035);
                --fp-border: rgba(255, 255, 255, 0.055);
                --fp-text: #E5E7EB;
                --fp-muted: rgba(229, 231, 235, 0.72);
                --fp-accent: #22D3EE;
                --fp-accent-soft: rgba(34, 211, 238, 0.1);
                background: var(--fp-bg);
                color: var(--fp-text);
                scroll-behavior: smooth;
            }

            .early-theme .hero-overlay {
                background:
                    radial-gradient(circle at 16% 14%, rgba(124, 58, 237, 0.25), transparent 34%),
                    radial-gradient(circle at 82% 8%, rgba(34, 211, 238, 0.12), transparent 26%),
                    linear-gradient(180deg, rgba(11, 12, 26, 0.96), rgba(11, 12, 26, 0.98));
            }

            .early-theme .page-shell {
                width: 100%;
                max-width: 82rem;
                margin-left: auto;
                margin-right: auto;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }

            @media (min-width: 1024px) {
                .early-theme .page-shell {
                    padding-left: 2rem;
                    padding-right: 2rem;
                }
            }

            .early-theme .surface {
                background: var(--fp-surface);
                border: 1px solid var(--fp-border);
                box-shadow: 0 22px 70px rgba(3, 7, 18, 0.38);
            }

            .early-theme .soft-card {
                background: var(--fp-surface-soft);
                border: 1px solid rgba(255, 255, 255, 0.04);
            }

            .early-theme .hero-badge {
                background: rgba(255, 255, 255, 0.05);
            }

            .early-theme .proof-chip {
                background: rgba(255, 255, 255, 0.045);
                border: 1px solid rgba(255, 255, 255, 0.045);
            }

            .early-theme .hero-visual {
                background:
                    linear-gradient(180deg, rgba(20, 22, 43, 0.88), rgba(13, 15, 31, 0.92)),
                    radial-gradient(circle at top right, rgba(34, 211, 238, 0.08), transparent 28%);
                border: 1px solid var(--fp-border);
                box-shadow: 0 28px 80px rgba(3, 7, 18, 0.42);
            }

            .early-theme .flow-node {
                background: rgba(255, 255, 255, 0.035);
            }

            .early-theme .flow-line {
                background: linear-gradient(90deg, rgba(124, 58, 237, 0.18), rgba(34, 211, 238, 0.55), rgba(124, 58, 237, 0.18));
            }

            .early-theme .highlight-card {
                background:
                    linear-gradient(180deg, rgba(25, 35, 48, 0.94), rgba(23, 28, 46, 0.98)),
                    radial-gradient(circle at top right, rgba(34, 211, 238, 0.1), transparent 32%);
                border: 1px solid rgba(34, 211, 238, 0.18);
                box-shadow: 0 18px 48px rgba(34, 211, 238, 0.08);
            }

            .early-theme .section-fade {
                background: linear-gradient(180deg, rgba(20, 22, 43, 0.6), rgba(11, 12, 26, 0.74));
            }
        </style>
    </head>
    <body class="early-theme min-h-screen antialiased">
        <div class="relative isolate overflow-hidden">
            <div class="hero-overlay pointer-events-none absolute inset-0 -z-10"></div>

            <header class="relative z-20">
                <div class="page-shell flex items-center justify-between py-6">
                    <a href="{{ route('early-access') }}" class="inline-flex items-center gap-3 text-sm font-semibold text-white">
                        <img src="{{ asset('images/logo-shield-phage-mark.svg') }}" alt="FirePhage logo" class="h-5 w-5" loading="eager" decoding="async">
                        <span>FirePhage</span>
                    </a>

                    <nav class="flex items-center gap-3 text-sm text-slate-300">
                        <a href="{{ route('contact') }}" class="rounded-lg border border-white/10 px-4 py-2 hover:border-cyan-300/60 hover:text-white">Contact</a>
                        <x-marketing.auth-aware-session-link class="rounded-lg border border-white/10 px-4 py-2 hover:border-cyan-300/60 hover:text-white" />
                    </nav>
                </div>
            </header>

            <main>
                <section class="relative overflow-hidden pb-28 pt-16 lg:pb-36 lg:pt-24">
                    <div class="page-shell">
                        <div class="grid grid-cols-1 gap-14 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.78fr)] lg:items-center lg:gap-18">
                            <div class="max-w-4xl">
                                <p class="inline-flex rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">
                                    Early Access
                                </p>
                                <h1 class="mt-10 max-w-4xl text-balance text-4xl font-semibold leading-[1.02] text-white sm:text-5xl xl:text-[4.2rem]">
                                    Managed edge protection for WordPress and WooCommerce sites that need real clarity, not security jargon.
                                </h1>
                                <p class="mt-9 max-w-3xl text-lg leading-8 text-slate-200/85 xl:text-[1.18rem]">
                                    FirePhage gives you managed WAF protection, origin IP shielding, uptime visibility, and clean onboarding in one place.
                                    It is built for teams who want to protect revenue-driving sites without stitching together complicated tools or figuring out DNS changes alone.
                                </p>

                                <div class="mt-10">
                                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-cyan-200/90">Perfect for</p>
                                    <div class="mt-5 flex flex-wrap gap-3.5">
                                        @foreach (['WordPress sites', 'WooCommerce stores', 'Agencies', 'High-traffic websites'] as $item)
                                            <span class="hero-badge rounded-full px-4 py-2 text-sm text-slate-200">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="mt-12 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center">
                                    <a href="#early-access-form" class="inline-flex items-center justify-center rounded-xl bg-cyan-400 px-6 py-3 text-sm font-semibold text-slate-950 shadow-[0_0_0_1px_rgba(34,211,238,0.24),0_0_26px_rgba(34,211,238,0.22)] transition hover:bg-cyan-300">
                                        Lock in launch pricing
                                    </a>
                                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl border border-white/10 px-6 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-300/60 hover:text-white">
                                        Talk to the team
                                    </a>
                                </div>
                            </div>

                            <div class="lg:pt-4">
                                <div class="hero-visual rounded-[2rem] p-8 sm:p-9">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-200">What teams get</p>
                                    <div class="mt-8 space-y-6">
                                        <div class="flow-node rounded-2xl p-5">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-medium text-white">Traffic reaches FirePhage edge</p>
                                                    <p class="mt-1 text-xs leading-5 text-slate-400">Managed WAF and origin shielding happen before traffic reaches your server.</p>
                                                </div>
                                                <span class="rounded-full bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">Managed</span>
                                            </div>
                                        </div>
                                        <div class="flow-line mx-auto h-px w-[82%]"></div>
                                        <div class="flow-node rounded-2xl p-5">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-medium text-white">Your team sees clear updates</p>
                                                    <p class="mt-1 text-xs leading-5 text-slate-400">Human-readable alerts, blocked attack summaries, and useful traffic visibility.</p>
                                                </div>
                                                <span class="rounded-full bg-white/6 px-3 py-1 text-xs font-semibold text-slate-200">Readable</span>
                                            </div>
                                        </div>
                                        <div class="flow-line mx-auto h-px w-[82%]"></div>
                                        <div class="flow-node rounded-2xl p-5">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-medium text-white">Onboarding help included</p>
                                                    <p class="mt-1 text-xs leading-5 text-slate-400">FirePhage can guide or handle DNS and setup so you do not have to do it alone.</p>
                                                </div>
                                                <span class="rounded-full bg-white/6 px-3 py-1 text-xs font-semibold text-slate-200">Included</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-8 flex flex-wrap gap-3.5">
                                        @foreach (['Launch pricing', 'Origin shielding', 'DNS help included'] as $item)
                                            <span class="rounded-full bg-white/[0.04] px-3 py-1.5 text-xs font-medium text-slate-200">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section-fade relative py-10 lg:py-12">
                    <div class="page-shell flex flex-wrap items-center gap-3">
                        @foreach (['Priority onboarding', 'Launch pricing locked in', 'Human-readable alerts', 'Origin protection'] as $item)
                            <div class="proof-chip rounded-full px-4 py-2 text-sm text-slate-200">{{ $item }}</div>
                        @endforeach
                    </div>
                </section>

                <section class="relative py-24 lg:py-32">
                    <div class="page-shell">
                        <div class="surface rounded-[2rem] p-8 sm:p-12 lg:p-14">
                            <div class="max-w-3xl">
                                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-cyan-200">Why join now</p>
                                <h2 class="mt-6 text-3xl font-semibold text-white sm:text-4xl">Early access without the usual rollout friction.</h2>
                                <p class="mt-6 text-base leading-7 text-slate-300">
                                    FirePhage is designed to protect serious WordPress and WooCommerce sites while staying understandable for owners, operators, and agencies. Join early to secure better pricing, faster onboarding, and a product that explains what matters clearly.
                                </p>
                            </div>

                            <div class="mt-12 grid gap-6 md:grid-cols-3 lg:gap-7">
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">Launch pricing</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">Secure the lowest pricing before the public rollout.</p>
                                </article>
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">Priority onboarding</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">Your sites are onboarded earlier as FirePhage opens access.</p>
                                </article>
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">Human-readable dashboard</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">Security events explained in plain language your team can understand.</p>
                                </article>
                            </div>

                            <div class="mt-7 grid gap-6 md:grid-cols-3 lg:gap-7">
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">No DNS guesswork</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">We guide the setup so teams do not struggle with DNS changes.</p>
                                </article>
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">No unreadable dashboards</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">Security events explained clearly instead of cryptic logs.</p>
                                </article>
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">No security noise</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">Important events are surfaced without overwhelming alerts.</p>
                                </article>
                            </div>

                            <div class="mt-7 grid gap-6 md:grid-cols-3 lg:gap-7">
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">Launch pricing</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">Join now and hold your early-access pricing window before wider rollout.</p>
                                </article>
                                <article class="soft-card rounded-[1.5rem] p-7">
                                    <h3 class="text-lg font-semibold text-white">Human-friendly dashboard</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">See what is happening without translating raw security noise into business meaning yourself.</p>
                                </article>
                                <article class="highlight-card rounded-[1.5rem] p-8">
                                    <h3 class="text-lg font-semibold text-white">Free assisted onboarding</h3>
                                    <p class="mt-3 text-sm leading-7 text-slate-200">We can handle DNS and setup for you at no extra cost, so activation is faster and less risky for your team.</p>
                                </article>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section-fade relative py-24 lg:py-32">
                    <div class="page-shell grid grid-cols-1 gap-16 lg:grid-cols-[0.95fr_1.05fr] lg:items-center lg:gap-20">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-cyan-200">Product Proof</p>
                            <h2 class="mt-6 text-3xl font-semibold text-white sm:text-4xl">A dashboard built for humans</h2>
                            <p class="mt-6 max-w-2xl text-base leading-7 text-slate-300">
                                FirePhage is designed to make protection understandable. You should be able to see what is being blocked, what matters, and what needs action without decoding a wall of security terms.
                            </p>

                            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:gap-7">
                                <div class="soft-card rounded-[1.2rem] p-8">
                                    <p class="text-base font-semibold text-white">Blocked attack summaries</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-400">Understand what was stopped and why it matters.</p>
                                </div>
                                <div class="soft-card rounded-[1.2rem] p-8">
                                    <p class="text-base font-semibold text-white">Simple explanations</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-400">See plain-language context instead of raw security noise.</p>
                                </div>
                                <div class="soft-card rounded-[1.2rem] p-8">
                                    <p class="text-base font-semibold text-white">Useful alerts</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-400">Get alerts that help you act, not alerts that waste your day.</p>
                                </div>
                                <div class="soft-card rounded-[1.2rem] p-8">
                                    <p class="text-base font-semibold text-white">Traffic visibility</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-400">Track protection, traffic patterns, and uptime posture in one place.</p>
                                </div>
                            </div>
                        </div>

                        <div class="relative pt-6 lg:pt-10">
                            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_68%_40%,rgba(34,211,238,0.18),transparent_60%)] blur-3xl opacity-75" aria-hidden="true"></div>
                            <div class="surface relative rounded-[2rem] p-6 sm:p-8">
                                <p class="mb-6 text-xs font-semibold uppercase tracking-[0.16em] text-cyan-200">Example FirePhage dashboard</p>
                                <div class="rounded-[1.35rem] bg-slate-950/82 p-6 shadow-[0_30px_90px_rgba(2,8,23,0.45)]">
                                    <div class="mb-6 flex items-center justify-between gap-3 border-b border-white/6 pb-6">
                                        <div>
                                            <p class="text-sm font-semibold text-white">Protection overview</p>
                                            <p class="mt-1 text-xs text-slate-400">Clear summaries for blocked traffic, alerts, and uptime status.</p>
                                        </div>
                                        <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">Origin protected</span>
                                    </div>
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <div class="soft-card rounded-[1rem] p-6">
                                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-200">Last 24 hours</p>
                                            <p class="mt-3 text-3xl font-semibold text-white">3.2M</p>
                                            <p class="mt-1 text-sm text-slate-400">Requests inspected</p>
                                        </div>
                                        <div class="soft-card rounded-[1rem] p-6">
                                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-200">Blocked threats</p>
                                            <p class="mt-3 text-3xl font-semibold text-white">18.4K</p>
                                            <p class="mt-1 text-sm text-slate-400">Bots, probes, and abusive requests stopped</p>
                                        </div>
                                        <div class="soft-card rounded-[1rem] p-6 sm:col-span-2">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-white">Most recent attacks</p>
                                                    <p class="mt-1 text-xs text-slate-400">Readable summaries instead of raw logs.</p>
                                                </div>
                                                <span class="rounded-full bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-300">2 blocked now</span>
                                            </div>
                                            <div class="mt-6 space-y-4">
                                                <div class="rounded-xl bg-slate-900/72 px-4 py-3">
                                                    <div class="flex items-center justify-between gap-3 text-sm">
                                                        <span class="font-medium text-white">Brute-force login attempts blocked</span>
                                                        <span class="text-slate-400">/wp-login.php</span>
                                                    </div>
                                                    <p class="mt-1 text-xs text-slate-400">339 requests stopped from 55 IPs targeting login access.</p>
                                                </div>
                                                <div class="rounded-xl bg-slate-900/72 px-4 py-3">
                                                    <div class="flex items-center justify-between gap-3 text-sm">
                                                        <span class="font-medium text-white">Origin shield active</span>
                                                        <span class="text-slate-400">No direct exposure</span>
                                                    </div>
                                                    <p class="mt-1 text-xs text-slate-400">Traffic is reaching the edge first, helping keep your origin IP hidden.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="early-access-form" class="relative py-24 lg:py-32">
                    <div class="page-shell grid grid-cols-1 gap-14 lg:grid-cols-[0.78fr_1.02fr] lg:items-start lg:gap-18">
                        <div class="lg:pt-6">
                            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-cyan-200">Join The List</p>
                            <h2 class="mt-6 text-3xl font-semibold text-white sm:text-4xl">Get the launch invite, onboarding priority, and early pricing access.</h2>
                            <p class="mt-6 max-w-xl text-base leading-7 text-slate-300">
                                Joining now puts you in the first launch cohort. We will use this list to offer early pricing, onboarding priority, direct launch invites, and setup guidance as FirePhage opens wider access.
                            </p>
                            <p class="mt-8 max-w-xl text-sm leading-7 text-slate-400">
                                Built from real-world experience securing WordPress and WooCommerce sites, FirePhage focuses on clarity, protection, and clean onboarding instead of security noise.
                            </p>

                            <div class="mt-12 space-y-6">
                                <div class="soft-card rounded-[1.5rem] p-6">
                                    <p class="text-sm font-medium text-white">What you get</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-400">Launch pricing access, onboarding priority, launch invitation, and setup guidance as access opens.</p>
                                </div>
                                <div class="soft-card rounded-[1.5rem] p-6">
                                    <p class="text-sm font-medium text-white">Who this is best for</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-400">Site owners, WooCommerce operators, and agencies that want cleaner protection without a heavy technical lift.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="surface rounded-[2rem] p-8 sm:p-12 lg:p-14">
                                <div class="mb-10">
                                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-200">Reserve Your Spot</p>
                                    <h3 class="mt-5 text-2xl font-semibold text-white">Request early access</h3>
                                    <p class="mt-5 max-w-2xl text-sm leading-6 text-slate-400">
                                        Share a few details so we can prioritize the right teams for launch pricing, onboarding help, and first-wave invites.
                                    </p>
                                </div>

                                @if (session('status'))
                                    <div class="mb-6 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm text-emerald-100">
                                        {{ session('status') }}
                                    </div>
                                @endif

                                @if ($errors->any())
                                    <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
                                        Please fix the highlighted fields and submit again.
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('early-access.store') }}" class="space-y-8">
                                    @csrf

                                    <div class="grid gap-7 sm:grid-cols-2">
                                        <div>
                                            <label for="name" class="mb-3 block text-sm font-medium text-slate-200">Full name</label>
                                            <input
                                                id="name"
                                                name="name"
                                                type="text"
                                                value="{{ old('name') }}"
                                                required
                                                class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-cyan-300"
                                                placeholder="Jane Founder"
                                            >
                                            @error('name')
                                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="email" class="mb-3 block text-sm font-medium text-slate-200">Work email</label>
                                            <input
                                                id="email"
                                                name="email"
                                                type="email"
                                                value="{{ old('email') }}"
                                                required
                                                class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-cyan-300"
                                                placeholder="jane@company.com"
                                            >
                                            @error('email')
                                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="grid gap-7 sm:grid-cols-2">
                                        <div>
                                            <label for="company_name" class="mb-3 block text-sm font-medium text-slate-200">Company</label>
                                            <input
                                                id="company_name"
                                                name="company_name"
                                                type="text"
                                                value="{{ old('company_name') }}"
                                                class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-cyan-300"
                                                placeholder="Acme Media"
                                            >
                                            @error('company_name')
                                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="website_url" class="mb-3 block text-sm font-medium text-slate-200">Website</label>
                                            <input
                                                id="website_url"
                                                name="website_url"
                                                type="url"
                                                value="{{ old('website_url') }}"
                                                class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-cyan-300"
                                                placeholder="https://example.com"
                                            >
                                            @error('website_url')
                                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="grid gap-7 sm:grid-cols-2">
                                        <div>
                                            <label for="monthly_requests_band" class="mb-3 block text-sm font-medium text-slate-200">Approximate monthly traffic</label>
                                            <select
                                                id="monthly_requests_band"
                                                name="monthly_requests_band"
                                                class="w-full rounded-2xl border border-white/10 bg-[#16182d] px-4 py-3 text-sm text-white outline-none focus:border-cyan-300"
                                            >
                                                <option value="">Select a range</option>
                                                @foreach (['Under 500k requests', '500k to 5M requests', '5M to 20M requests', '20M+ requests', 'Not sure yet'] as $option)
                                                    <option value="{{ $option }}" @selected(old('monthly_requests_band') === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            @error('monthly_requests_band')
                                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="websites_managed" class="mb-3 block text-sm font-medium text-slate-200">How many websites do you manage?</label>
                                            <select
                                                id="websites_managed"
                                                name="websites_managed"
                                                class="w-full rounded-2xl border border-white/10 bg-[#16182d] px-4 py-3 text-sm text-white outline-none focus:border-cyan-300"
                                            >
                                                <option value="">Select an option</option>
                                                @foreach (['1 website', '2-5 websites', '6-20 websites', '21+ websites'] as $option)
                                                    <option value="{{ $option }}" @selected(old('websites_managed') === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            @error('websites_managed')
                                                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div>
                                        <label for="notes" class="mb-3 block text-sm font-medium text-slate-200">What would make FirePhage most useful for you?</label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows="4"
                                            class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-cyan-300"
                                            placeholder="Tell us about your hosting setup, client environment, current pain points, or the type of protection and visibility you want most."
                                        >{{ old('notes') }}</textarea>
                                        @error('notes')
                                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 p-5">
                                        <input
                                            type="checkbox"
                                            name="wants_launch_discount"
                                            value="1"
                                            @checked(old('wants_launch_discount', '1') === '1')
                                            class="mt-1 h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-400 focus:ring-cyan-300"
                                        >
                                        <span class="text-sm leading-6 text-slate-200">
                                            Keep me on the launch pricing and early-discount list.
                                        </span>
                                    </label>

                                    <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-5 py-3.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                                        Reserve early access
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
