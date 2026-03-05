<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>FirePhage | Shield Your Origin and Control Edge Traffic</title>
        <meta name="description" content="FirePhage combines WAF (Firewall rules), origin IP protection, and availability monitoring in one simple dashboard.">
        <meta property="og:type" content="website">
        <meta property="og:title" content="FirePhage | Shield Your Origin and Control Edge Traffic">
        <meta property="og:description" content="WAF (Firewall rules), origin IP protection, and monitoring in one clear dashboard.">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:site_name" content="FirePhage">
        <meta name="theme-color" content="#030712">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .theme-v2 {
                --fp-bg: #06110C;
                --fp-bg-2: #0E1F18;
                --fp-card: #132922;
                --fp-accent: #22C55E;
                --fp-accent-hover: #4ADE80;
                --fp-text: #E8F5EC;
                --fp-border: rgba(255, 255, 255, 0.06);
                --fp-glow: rgba(34, 197, 94, 0.25);
                --fp-btn-text: #06110C;
                background: var(--fp-bg);
                color: var(--fp-text);
            }

            .theme-v2 .relative.isolate.overflow-hidden > .pointer-events-none.absolute.inset-0.-z-10 {
                background: radial-gradient(circle at 20% 20%, rgba(34, 197, 94, 0.2), transparent 38%), radial-gradient(circle at 80% 0%, rgba(74, 222, 128, 0.14), transparent 34%), linear-gradient(to bottom, #06110C, #06110C) !important;
            }

            .theme-v2 main > section {
                background: var(--fp-bg) !important;
                border-color: var(--fp-border) !important;
            }

            .theme-v2 main > section:nth-of-type(even) {
                background: var(--fp-bg-2) !important;
            }

            .theme-v2 main > section:first-of-type > .pointer-events-none.absolute.inset-0.z-0 > .absolute.inset-0 {
                background: radial-gradient(circle at 74% 28%, rgba(34, 197, 94, 0.24), transparent 44%), linear-gradient(90deg, rgba(6, 17, 12, 0.97) 0%, rgba(6, 17, 12, 0.9) 36%, rgba(6, 17, 12, 0.5) 66%, rgba(6, 17, 12, 0.2) 100%) !important;
            }

            .theme-v2 [class*="text-white"] { color: var(--fp-text) !important; }
            .theme-v2 [class*="text-slate-"] { color: rgba(232, 245, 236, 0.78) !important; }
            .theme-v2 [class*="text-cyan-"] { color: var(--fp-accent-hover) !important; }
            .theme-v2 [class*="border-cyan-"] { border-color: var(--fp-accent) !important; }
            .theme-v2 [class*="bg-cyan-"][class*="/"] { background-color: rgba(34, 197, 94, 0.14) !important; }

            .theme-v2 main section article,
            .theme-v2 main section .rounded-2xl.border {
                background: var(--fp-card) !important;
                border-color: var(--fp-border) !important;
            }

            .theme-v2 main section article:hover,
            .theme-v2 main section .rounded-2xl.border:hover {
                border-color: var(--fp-accent) !important;
                box-shadow: 0 0 18px var(--fp-glow) !important;
            }

            .theme-v2 a[class*="bg-cyan-"] {
                background: var(--fp-accent) !important;
                color: var(--fp-btn-text) !important;
                border-color: transparent !important;
            }

            .theme-v2 a[class*="bg-cyan-"]:hover {
                background: var(--fp-accent-hover) !important;
                box-shadow: 0 0 16px var(--fp-glow) !important;
                color: var(--fp-btn-text) !important;
            }

            .theme-v2 a[class*="border-slate-"],
            .theme-v2 a[class*="border-cyan-"][class*="bg-transparent"],
            .theme-v2 a[href="#dashboard-preview"] {
                border-color: var(--fp-accent) !important;
                color: var(--fp-accent-hover) !important;
                background: transparent !important;
            }

            .theme-v2 a[href="#dashboard-preview"]:hover {
                box-shadow: 0 0 16px var(--fp-glow) !important;
                background: rgba(34, 197, 94, 0.1) !important;
            }

            .theme-v2 a:not([class*="bg-cyan-"]):hover {
                color: var(--fp-accent-hover) !important;
            }
        </style>
    </head>
    <body class="theme-v2 bg-slate-950 text-slate-100 antialiased">
        <div class="relative isolate overflow-hidden">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_20%,rgba(14,165,233,0.24),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(56,189,248,0.18),transparent_30%),linear-gradient(to_bottom,rgba(2,6,23,1),rgba(3,7,18,1))]"></div>

            <header class="mx-auto w-full max-w-7xl px-6 py-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-cyan-300">
                        <img src="{{ asset('images/logo-shield-phage-mark.svg') }}" alt="FirePhage logo" class="h-5 w-5" loading="eager" decoding="async">
                        <span>FirePhage</span>
                    </a>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-white/10 p-2 text-slate-200 hover:border-cyan-300/60 hover:text-white md:hidden"
                        aria-label="Open menu"
                        aria-controls="mobile-menu"
                        aria-expanded="false"
                        data-mobile-menu-toggle
                    >
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>

                    <nav class="hidden items-center gap-5 text-sm text-slate-300 md:flex">
                        <a href="#features" class="hover:text-white">Features</a>
                        <a href="#pricing" class="hover:text-white">Pricing</a>
                        <a href="{{ url('/login') }}" class="hover:text-white">Login</a>
                        <a href="{{ url('/register') }}" class="rounded-lg bg-cyan-500 px-4 py-2 font-medium text-slate-950 hover:bg-cyan-400">Start Free</a>
                    </nav>
                </div>

                <nav id="mobile-menu" class="mt-4 hidden rounded-xl border border-white/10 bg-slate-900/95 p-3 text-sm text-slate-200 md:hidden" data-mobile-menu>
                    <a href="#features" class="block rounded-lg px-3 py-2 hover:bg-slate-800/80">Features</a>
                    <a href="#pricing" class="mt-1 block rounded-lg px-3 py-2 hover:bg-slate-800/80">Pricing</a>
                    <a href="{{ url('/login') }}" class="mt-1 block rounded-lg px-3 py-2 hover:bg-slate-800/80">Login</a>
                    <a href="{{ url('/register') }}" class="mt-3 block rounded-lg bg-cyan-500 px-4 py-2 text-center font-medium text-slate-950 hover:bg-cyan-400">Start Free</a>
                </nav>
            </header>

            <main>
                <x-marketing.hero />
                <x-marketing.human-friendly-onboarding />
                <x-marketing.safe-dns-cutover />
                <x-marketing.security-dashboard-section />
                <x-marketing.edge-protection-numbers />
                <x-marketing.global-edge-protection />
                <x-marketing.features />
                <x-marketing.platform-architecture />
                <x-marketing.pricing />
            </main>

            <x-marketing.footer />
        </div>
        <script>
            (() => {
                const button = document.querySelector('[data-mobile-menu-toggle]');
                const menu = document.querySelector('[data-mobile-menu]');

                if (!button || !menu) {
                    return;
                }

                const closeMenu = () => {
                    menu.classList.add('hidden');
                    button.setAttribute('aria-expanded', 'false');
                };

                const openMenu = () => {
                    menu.classList.remove('hidden');
                    button.setAttribute('aria-expanded', 'true');
                };

                button.addEventListener('click', () => {
                    if (menu.classList.contains('hidden')) {
                        openMenu();
                    } else {
                        closeMenu();
                    }
                });

                menu.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', closeMenu);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeMenu();
                    }
                });
            })();
        </script>
    </body>
</html>
