<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="FirePhage Logo Concepts"
            description="Logo concept board for FirePhage branding selection."
            :canonical="route('logos')"
            robots="noindex,follow"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_10%,rgba(8,145,178,0.20),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(37,99,235,0.16),transparent_30%),linear-gradient(to_bottom,rgba(2,6,23,1),rgba(3,7,18,1))]"></div>

            <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-cyan-300">
                    <img src="{{ asset('images/logo-shield-phage-mark.svg') }}" alt="FirePhage logo" class="h-[1.3125rem] w-[1.3125rem]" loading="eager" decoding="async">
                    <span>FirePhage</span>
                </a>
                <a href="{{ url('/') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:border-slate-500">Back to Home</a>
            </header>

            <main class="mx-auto w-full max-w-7xl px-6 pb-16 pt-4 lg:px-8">
                <div class="max-w-3xl">
                    <p class="inline-flex rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Brand Explorations</p>
                    <h1 class="mt-4 text-3xl font-semibold text-white sm:text-4xl">FirePhage Logo Concepts</h1>
                    <p class="mt-3 text-slate-300">Six directions: three phage-inspired and three clean SaaS marks. Pick a number and I will refine that one into production-ready variants.</p>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 1: Hex phage">
                                <defs>
                                    <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#22d3ee" />
                                        <stop offset="100%" stop-color="#2563eb" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(30,20)">
                                    <polygon points="42,0 84,24 84,72 42,96 0,72 0,24" fill="none" stroke="url(#g1)" stroke-width="6"/>
                                    <line x1="42" y1="96" x2="42" y2="118" stroke="#22d3ee" stroke-width="5"/>
                                    <line x1="42" y1="118" x2="26" y2="132" stroke="#22d3ee" stroke-width="4"/>
                                    <line x1="42" y1="118" x2="58" y2="132" stroke="#22d3ee" stroke-width="4"/>
                                </g>
                                <text x="130" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="130" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Edge Security</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">1. Hex Phage</h2>
                        <p class="mt-1 text-xs text-slate-400">Direct phage symbol. Strong biotech + security identity.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 2: Shield phage">
                                <defs>
                                    <linearGradient id="g2" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#38bdf8" />
                                        <stop offset="100%" stop-color="#0ea5e9" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(24,18)">
                                    <path d="M48 0 L90 18 L90 56 C90 82 71 101 48 112 C25 101 6 82 6 56 L6 18 Z" fill="none" stroke="url(#g2)" stroke-width="6"/>
                                    <polygon points="48,24 64,34 64,52 48,62 32,52 32,34" fill="none" stroke="#7dd3fc" stroke-width="4"/>
                                    <line x1="48" y1="62" x2="48" y2="78" stroke="#7dd3fc" stroke-width="4"/>
                                    <line x1="48" y1="78" x2="38" y2="90" stroke="#7dd3fc" stroke-width="3"/>
                                    <line x1="48" y1="78" x2="58" y2="90" stroke="#7dd3fc" stroke-width="3"/>
                                </g>
                                <text x="126" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="126" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Shielded by design</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">2. Shield Phage</h2>
                        <p class="mt-1 text-xs text-slate-400">Combines a protective shield with a stylized phage core.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 3: Monogram FP phage">
                                <defs>
                                    <linearGradient id="g3" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#22d3ee" />
                                        <stop offset="100%" stop-color="#3b82f6" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(20,16)">
                                    <rect x="0" y="0" width="106" height="106" rx="20" fill="none" stroke="url(#g3)" stroke-width="6"/>
                                    <path d="M24 82 V24 H74" stroke="#67e8f9" stroke-width="8" fill="none" stroke-linecap="round"/>
                                    <path d="M52 82 V44 H82" stroke="#60a5fa" stroke-width="8" fill="none" stroke-linecap="round"/>
                                    <circle cx="86" cy="82" r="8" fill="#60a5fa"/>
                                </g>
                                <text x="140" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="140" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">FP Monogram</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">3. FP Monogram</h2>
                        <p class="mt-1 text-xs text-slate-400">Clean app icon style, more SaaS-native, less literal biology.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 4: Orbit phage">
                                <defs>
                                    <linearGradient id="g4" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#0ea5e9" />
                                        <stop offset="100%" stop-color="#38bdf8" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(22,18)">
                                    <circle cx="52" cy="52" r="34" fill="none" stroke="url(#g4)" stroke-width="6"/>
                                    <ellipse cx="52" cy="52" rx="50" ry="20" fill="none" stroke="#38bdf8" stroke-width="3" opacity="0.8"/>
                                    <polygon points="52,30 64,38 64,52 52,60 40,52 40,38" fill="none" stroke="#7dd3fc" stroke-width="3"/>
                                    <line x1="52" y1="60" x2="52" y2="78" stroke="#7dd3fc" stroke-width="3"/>
                                    <circle cx="96" cy="46" r="4" fill="#22d3ee"/>
                                </g>
                                <text x="132" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="132" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Network orbit</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">4. Orbit Phage</h2>
                        <p class="mt-1 text-xs text-slate-400">Signals traffic orbit/coverage with a phage nucleus motif.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 5: Flame shield">
                                <defs>
                                    <linearGradient id="g5" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#22d3ee" />
                                        <stop offset="100%" stop-color="#2563eb" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(22,16)">
                                    <path d="M54 0 C70 16 82 34 82 54 C82 83 66 106 38 106 C15 106 0 88 0 66 C0 43 18 25 36 15 C37 27 43 35 54 40 C52 28 52 16 54 0 Z" fill="none" stroke="url(#g5)" stroke-width="6"/>
                                    <path d="M36 78 C44 76 50 68 50 58 C50 52 48 47 44 42 C56 47 64 57 64 69 C64 83 54 94 38 94 C27 94 18 86 18 75 C18 67 23 60 31 56 C30 62 31 70 36 78 Z" fill="#38bdf8" opacity="0.22"/>
                                </g>
                                <text x="126" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="126" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Flame + shield abstract</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">5. Flame Shield</h2>
                        <p class="mt-1 text-xs text-slate-400">More abstract brand mark; strong for app icon + favicon use.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 6: Wordmark clean">
                                <defs>
                                    <linearGradient id="g6" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#67e8f9" />
                                        <stop offset="100%" stop-color="#3b82f6" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(18,30)">
                                    <rect x="0" y="0" width="84" height="84" rx="18" fill="none" stroke="#334155" stroke-width="2"/>
                                    <path d="M24 68 V16 H66" stroke="url(#g6)" stroke-width="8" fill="none" stroke-linecap="round"/>
                                    <path d="M44 68 V38 H70" stroke="#7dd3fc" stroke-width="7" fill="none" stroke-linecap="round"/>
                                </g>
                                <text x="122" y="70" fill="#f8fafc" font-size="30" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="122" y="92" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Clean enterprise wordmark</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">6. Clean Wordmark</h2>
                        <p class="mt-1 text-xs text-slate-400">Minimal enterprise mark for docs, footer, and app chrome.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 7: Phage crest">
                                <defs>
                                    <linearGradient id="g7" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#22d3ee" />
                                        <stop offset="100%" stop-color="#2563eb" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(24,14)">
                                    <path d="M52 0 L96 22 V62 C96 88 76 108 52 118 C28 108 8 88 8 62 V22 Z" fill="none" stroke="url(#g7)" stroke-width="6"/>
                                    <polygon points="52,28 66,36 66,52 52,60 38,52 38,36" fill="none" stroke="#7dd3fc" stroke-width="3"/>
                                    <line x1="52" y1="60" x2="52" y2="80" stroke="#7dd3fc" stroke-width="3"/>
                                    <line x1="52" y1="80" x2="40" y2="94" stroke="#7dd3fc" stroke-width="3"/>
                                    <line x1="52" y1="80" x2="64" y2="94" stroke="#7dd3fc" stroke-width="3"/>
                                </g>
                                <text x="128" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="128" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Crest-style emblem</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">7. Phage Crest</h2>
                        <p class="mt-1 text-xs text-slate-400">Badge-like icon for strong trust and enterprise feel.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 8: Signal lattice">
                                <defs>
                                    <linearGradient id="g8" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#67e8f9" />
                                        <stop offset="100%" stop-color="#3b82f6" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(18,18)">
                                    <rect x="0" y="0" width="98" height="98" rx="20" fill="none" stroke="#334155" stroke-width="2"/>
                                    <circle cx="24" cy="28" r="5" fill="#67e8f9"/>
                                    <circle cx="50" cy="18" r="5" fill="#60a5fa"/>
                                    <circle cx="78" cy="30" r="5" fill="#22d3ee"/>
                                    <circle cx="70" cy="62" r="5" fill="#3b82f6"/>
                                    <circle cx="38" cy="74" r="5" fill="#67e8f9"/>
                                    <line x1="24" y1="28" x2="50" y2="18" stroke="url(#g8)" stroke-width="3"/>
                                    <line x1="50" y1="18" x2="78" y2="30" stroke="url(#g8)" stroke-width="3"/>
                                    <line x1="78" y1="30" x2="70" y2="62" stroke="url(#g8)" stroke-width="3"/>
                                    <line x1="70" y1="62" x2="38" y2="74" stroke="url(#g8)" stroke-width="3"/>
                                    <line x1="38" y1="74" x2="24" y2="28" stroke="url(#g8)" stroke-width="3"/>
                                </g>
                                <text x="130" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="130" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Traffic graph motif</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">8. Signal Lattice</h2>
                        <p class="mt-1 text-xs text-slate-400">Network-defense vibe, cleaner for dashboard-native branding.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4">
                            <svg viewBox="0 0 320 140" class="h-28 w-full" role="img" aria-label="Concept 9: Minimal phage spark">
                                <defs>
                                    <linearGradient id="g9" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#22d3ee" />
                                        <stop offset="100%" stop-color="#60a5fa" />
                                    </linearGradient>
                                </defs>
                                <g transform="translate(20,20)">
                                    <circle cx="46" cy="40" r="30" fill="none" stroke="url(#g9)" stroke-width="6"/>
                                    <polygon points="46,24 58,32 58,46 46,54 34,46 34,32" fill="none" stroke="#93c5fd" stroke-width="3"/>
                                    <line x1="46" y1="54" x2="46" y2="72" stroke="#93c5fd" stroke-width="3"/>
                                    <line x1="46" y1="72" x2="34" y2="84" stroke="#93c5fd" stroke-width="3"/>
                                    <line x1="46" y1="72" x2="58" y2="84" stroke="#93c5fd" stroke-width="3"/>
                                    <circle cx="82" cy="32" r="4" fill="#22d3ee"/>
                                </g>
                                <text x="128" y="66" fill="#f8fafc" font-size="28" font-family="ui-sans-serif, system-ui" font-weight="700">FirePhage</text>
                                <text x="128" y="88" fill="#94a3b8" font-size="12" font-family="ui-sans-serif, system-ui">Minimal phage spark</text>
                            </svg>
                        </div>
                        <h2 class="mt-4 text-sm font-semibold text-white">9. Phage Spark</h2>
                        <p class="mt-1 text-xs text-slate-400">Minimal mark with subtle phage DNA for modern SaaS look.</p>
                    </article>
                </div>
            </main>
        </div>
    </body>
</html>
