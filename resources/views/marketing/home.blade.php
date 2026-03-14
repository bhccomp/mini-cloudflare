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
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
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
                        <x-marketing.auth-aware-session-link class="hover:text-white" />
                        <x-marketing.auth-aware-link guest-label="Start Free" class="rounded-lg bg-cyan-500 px-4 py-2 font-medium text-slate-950 hover:bg-cyan-400" />
                    </nav>
                </div>

                <nav id="mobile-menu" class="mt-4 hidden rounded-xl border border-white/10 bg-slate-900/95 p-3 text-sm text-slate-200 md:hidden" data-mobile-menu>
                    <a href="#features" class="block rounded-lg px-3 py-2 hover:bg-slate-800/80">Features</a>
                    <a href="#pricing" class="mt-1 block rounded-lg px-3 py-2 hover:bg-slate-800/80">Pricing</a>
                    <x-marketing.auth-aware-session-link class="mt-1 block w-full rounded-lg px-3 py-2 text-left hover:bg-slate-800/80" />
                    <x-marketing.auth-aware-link guest-label="Start Free" class="mt-3 block rounded-lg bg-cyan-500 px-4 py-2 text-center font-medium text-slate-950 hover:bg-cyan-400" />
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
