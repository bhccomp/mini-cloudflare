<footer class="border-t border-slate-800 bg-slate-950/90">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 px-6 py-8 text-sm text-slate-400 lg:px-8 xl:flex-row xl:items-center xl:justify-between">
        <p class="text-sm leading-6 text-slate-400">
            &copy; 2026 FirePhage. Operated by Dialbotics LLC.
        </p>
        <nav class="flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('about') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">About</a>
            <a href="{{ route('blog.index') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">Blog</a>
            <a href="{{ route('terms') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">Terms</a>
            <a href="{{ route('privacy') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">Privacy</a>
            <a href="{{ route('cookies') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">Cookies</a>
            <a href="{{ route('refund-policy') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">Refunds</a>
            <a href="{{ route('acceptable-use') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">Acceptable Use</a>
            <a href="{{ route('contact') }}" class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200">Contact</a>
            <x-marketing.auth-aware-session-link class="rounded-full border border-white/8 px-3 py-1.5 transition hover:border-white/15 hover:text-slate-200" />
        </nav>
    </div>
</footer>
<x-marketing.cookie-consent />
