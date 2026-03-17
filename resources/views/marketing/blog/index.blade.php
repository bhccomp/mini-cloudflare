<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="FirePhage Blog | WordPress Protection, Firewalls, and Bot Attacks"
            description="Clear FirePhage articles about WordPress protection, firewall strategy, bot attacks, WooCommerce security, origin protection, and practical onboarding guidance."
            :canonical="route('blog.index')"
            :og-url="route('blog.index')"
            :structured-data="[
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Blog',
                    'name' => 'FirePhage Blog',
                    'url' => route('blog.index'),
                    'description' => 'Clear articles about WordPress protection, firewall strategy, bot attacks, and practical onboarding guidance.',
                ],
            ]"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.14),transparent_34%),linear-gradient(180deg,rgba(2,8,23,0.96),rgba(2,6,23,1))]"></div>
            </div>

            <div class="relative z-10">
                <x-marketing.site-header />

                <main>
                    <section class="mx-auto w-full max-w-7xl px-6 pb-10 pt-16 lg:px-8 lg:pb-14 lg:pt-24">
                        <div class="max-w-4xl">
                            <p class="inline-flex rounded-full border border-cyan-400/25 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">FirePhage Blog</p>
                            <h1 class="mt-6 text-balance text-4xl font-semibold leading-tight text-white lg:text-6xl">Clear writing about WordPress protection, bot attacks, and firewall decisions that actually matter.</h1>
                            <p class="mt-6 max-w-3xl text-lg leading-8 text-slate-300">This blog is where FirePhage explains how to protect WordPress and WooCommerce sites without drowning teams in security jargon. The goal is practical, product-adjacent content that helps site owners and agencies make better protection decisions.</p>
                        </div>

                        @if ($categories->isNotEmpty())
                            <div class="mt-8 flex flex-wrap gap-3">
                                @foreach ($categories as $category)
                                    <span class="rounded-full border border-white/10 bg-slate-900/70 px-4 py-2 text-sm text-slate-200">{{ $category->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($featuredPost)
                        <section class="mx-auto w-full max-w-7xl px-6 pb-8 lg:px-8">
                            <a href="{{ route('blog.show', $featuredPost) }}" class="block overflow-hidden rounded-3xl border border-white/10 bg-slate-900/80 p-8 shadow-[0_24px_80px_rgba(2,8,23,0.45)] transition hover:border-cyan-300/50">
                                <div class="grid gap-8 lg:grid-cols-[1.4fr_0.8fr] lg:items-end">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">Featured article</p>
                                        <h2 class="mt-4 text-3xl font-semibold leading-tight text-white lg:text-4xl">{{ $featuredPost->title }}</h2>
                                        <p class="mt-5 max-w-3xl text-base leading-7 text-slate-300">{{ $featuredPost->excerpt }}</p>
                                        <div class="mt-6 flex flex-wrap items-center gap-4 text-sm text-slate-400">
                                            @if ($featuredPost->category)
                                                <span>{{ $featuredPost->category->name }}</span>
                                            @endif
                                            <span>{{ $featuredPost->publishedLabel() }}</span>
                                            <span>{{ $featuredPost->readingTimeMinutes() }} min read</span>
                                        </div>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-6">
                                        <p class="text-sm font-medium text-white">Why this content matters</p>
                                        <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-300">
                                            <li>Plain-language guidance for agencies and WordPress site owners.</li>
                                            <li>Practical explanations of attacks, DNS changes, and onboarding.</li>
                                            <li>Security writing tied directly to how FirePhage is built.</li>
                                        </ul>
                                    </div>
                                </div>
                            </a>
                        </section>
                    @endif

                    <section class="mx-auto w-full max-w-7xl px-6 pb-16 lg:px-8 lg:pb-24">
                        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                            @forelse ($posts as $post)
                                <article class="flex h-full flex-col rounded-3xl border border-white/10 bg-slate-900/75 p-7">
                                    <div class="flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">
                                        @if ($post->category)
                                            <span>{{ $post->category->name }}</span>
                                        @endif
                                        <span class="text-slate-600">•</span>
                                        <span class="text-slate-400">{{ $post->publishedLabel() }}</span>
                                    </div>
                                    <h2 class="mt-4 text-2xl font-semibold leading-tight text-white">
                                        <a href="{{ route('blog.show', $post) }}" class="hover:text-cyan-200">{{ $post->title }}</a>
                                    </h2>
                                    <p class="mt-4 flex-1 text-sm leading-7 text-slate-300">{{ $post->excerpt }}</p>
                                    <div class="mt-6 flex items-center justify-between text-sm text-slate-400">
                                        <span>{{ $post->readingTimeMinutes() }} min read</span>
                                        <a href="{{ route('blog.show', $post) }}" class="font-medium text-cyan-300 hover:text-cyan-200">Read article</a>
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-3xl border border-white/10 bg-slate-900/75 p-8 text-sm text-slate-300 md:col-span-2 xl:col-span-3">
                                    No blog posts are published yet. The structure is ready, but content has not been released.
                                </div>
                            @endforelse
                        </div>

                        @if ($posts->hasPages())
                            <div class="mt-10">
                                {{ $posts->links() }}
                            </div>
                        @endif
                    </section>
                </main>

                <x-marketing.footer-variant-1 />
            </div>
        </div>
    </body>
</html>
