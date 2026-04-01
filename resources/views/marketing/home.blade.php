<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="FirePhage | Managed WAF, Origin Protection, and Human-Readable Edge Security"
            description="FirePhage helps WordPress, WooCommerce, and agency teams protect websites with managed WAF controls, origin IP shielding, bot protection, and clear operational visibility."
            :canonical="route('home.blue')"
            :og-url="route('home.blue')"
            robots="noindex,follow"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative isolate overflow-hidden">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_20%,rgba(14,165,233,0.24),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(56,189,248,0.18),transparent_30%),linear-gradient(to_bottom,rgba(2,6,23,1),rgba(3,7,18,1))]"></div>

            <x-marketing.site-header />

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
    </body>
</html>
