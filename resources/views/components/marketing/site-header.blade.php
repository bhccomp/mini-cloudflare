<header class="relative z-30 mx-auto w-full max-w-7xl px-6 py-6 lg:px-8">
    <div class="flex items-center justify-between">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-cyan-300">
            <img src="{{ asset('images/logo-shield-phage-mark.svg') }}" alt="FirePhage logo" class="h-5 w-5" loading="eager" decoding="async">
            <span>FirePhage</span>
        </a>

        <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-white/10 p-2 text-slate-200 hover:border-cyan-300/60 hover:text-white md:hidden"
            aria-label="Open menu"
            aria-controls="marketing-mobile-menu"
            aria-expanded="false"
            data-marketing-mobile-menu-toggle
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>

        <nav class="hidden items-center gap-5 text-sm text-slate-300 md:flex">
            <x-marketing.services-dropdown />
            <a href="{{ route('home') }}#features" class="hover:text-white">Features</a>
            <a href="{{ route('home') }}#pricing" class="hover:text-white">Pricing</a>
            <a href="{{ route('blog.index') }}" class="hover:text-white">Blog</a>
            <a href="{{ route('contact') }}" class="hover:text-white">Contact</a>
            <x-marketing.auth-aware-session-link class="hover:text-white" />
            <x-marketing.auth-aware-link guest-label="Start Free" class="rounded-lg bg-cyan-500 px-4 py-2 font-medium text-slate-950 hover:bg-cyan-400" />
        </nav>
    </div>

    <nav id="marketing-mobile-menu" class="mt-4 hidden rounded-xl border border-white/10 bg-slate-900/95 p-3 text-sm text-slate-200 md:hidden" data-marketing-mobile-menu>
        <x-marketing.services-mobile-links />
        <a href="{{ route('home') }}#features" class="block rounded-lg px-3 py-2 hover:bg-slate-800/80">Features</a>
        <a href="{{ route('home') }}#pricing" class="mt-1 block rounded-lg px-3 py-2 hover:bg-slate-800/80">Pricing</a>
        <a href="{{ route('blog.index') }}" class="mt-1 block rounded-lg px-3 py-2 hover:bg-slate-800/80">Blog</a>
        <a href="{{ route('contact') }}" class="mt-1 block rounded-lg px-3 py-2 hover:bg-slate-800/80">Contact</a>
        <x-marketing.auth-aware-session-link class="mt-1 block w-full rounded-lg px-3 py-2 text-left hover:bg-slate-800/80" />
        <x-marketing.auth-aware-link guest-label="Start Free" class="mt-3 block rounded-lg bg-cyan-500 px-4 py-2 text-center font-medium text-slate-950 hover:bg-cyan-400" />
    </nav>
</header>
