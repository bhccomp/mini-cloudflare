<footer class="border-t border-slate-800 bg-slate-950/90">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <p>&copy; {{ now()->year }} FirePhage. All rights reserved. Built for WordPress and WooCommerce security at the edge.</p>
        <nav class="flex items-center gap-5">
            <a href="{{ route('blog.index') }}" class="hover:text-slate-200">Blog</a>
            <a href="{{ route('terms') }}" class="hover:text-slate-200">Terms</a>
            <a href="{{ route('privacy') }}" class="hover:text-slate-200">Privacy</a>
            <a href="{{ route('refund-policy') }}" class="hover:text-slate-200">Refunds</a>
            <a href="{{ route('acceptable-use') }}" class="hover:text-slate-200">Acceptable Use</a>
            <a href="{{ route('contact') }}" class="hover:text-slate-200">Contact</a>
            <x-marketing.auth-aware-session-link class="hover:text-slate-200" />
        </nav>
    </div>
</footer>
