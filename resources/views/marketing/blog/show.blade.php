<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            :title="$post->seoTitle()"
            :description="$post->seoDescription()"
            og-type="article"
            :canonical="$post->canonical_url ?: route('blog.show', $post)"
            :og-url="$post->canonical_url ?: route('blog.show', $post)"
            :og-image="$post->og_image_url"
            :og-image-alt="$post->ogImageAlt()"
            :structured-data="[
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BlogPosting',
                    'headline' => $post->title,
                    'description' => $post->seoDescription(),
                    'datePublished' => optional($post->published_at ?? $post->created_at)->toAtomString(),
                    'dateModified' => optional($post->updated_at ?? $post->published_at ?? $post->created_at)->toAtomString(),
                    'mainEntityOfPage' => $post->canonical_url ?: route('blog.show', $post),
                    'url' => $post->canonical_url ?: route('blog.show', $post),
                    'author' => [
                        '@type' => 'Organization',
                        'name' => 'FirePhage',
                    ],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => 'FirePhage',
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => asset('images/logo-shield-phage-wordmark.svg'),
                        ],
                    ],
                    'image' => $post->og_image_url ?: asset('images/dashboard-preview.png'),
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'FirePhage',
                            'item' => route('home'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Blog',
                            'item' => route('blog.index'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $post->title,
                            'item' => $post->canonical_url ?: route('blog.show', $post),
                        ],
                    ],
                ],
            ]"
        />
        <meta property="article:published_time" content="{{ optional($post->published_at ?? $post->created_at)->toAtomString() }}">
        <meta property="article:modified_time" content="{{ optional($post->updated_at ?? $post->published_at ?? $post->created_at)->toAtomString() }}">
        @if ($post->category)
            <meta property="article:section" content="{{ $post->category->name }}">
        @endif
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.12),transparent_32%),linear-gradient(180deg,rgba(2,8,23,0.96),rgba(2,6,23,1))]"></div>
            </div>

            <div class="relative z-10">
                <x-marketing.site-header />

                <main class="mx-auto w-full max-w-7xl px-6 pb-16 pt-14 lg:px-8 lg:pb-24 lg:pt-20">
                    <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_320px]">
                        <article class="rounded-3xl border border-white/10 bg-slate-900/80 p-8 shadow-[0_24px_80px_rgba(2,8,23,0.45)] lg:p-12">
                            <a href="{{ route('blog.index') }}" class="text-sm text-cyan-300 hover:text-cyan-200">&larr; Back to blog</a>

                            <div class="mt-8 flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-[0.14em] text-cyan-300">
                                @if ($post->category)
                                    <span>{{ $post->category->name }}</span>
                                @endif
                                <span class="text-slate-600">•</span>
                                <span class="text-slate-400">{{ $post->publishedLabel() }}</span>
                                <span class="text-slate-600">•</span>
                                <span class="text-slate-400">{{ $post->readingTimeMinutes() }} min read</span>
                            </div>

                            <h1 class="mt-5 text-balance text-4xl font-semibold leading-tight text-white lg:text-5xl">{{ $post->title }}</h1>

                            @if ($post->excerpt)
                                <p class="mt-6 max-w-3xl text-lg leading-8 text-slate-300">{{ $post->excerpt }}</p>
                            @endif

                            <div class="blog-article-content mt-10 max-w-none">
                                {!! \Illuminate\Support\Str::markdown($post->content_markdown) !!}
                            </div>
                        </article>

                        <aside class="space-y-6">
                            <div class="rounded-3xl border border-white/10 bg-slate-900/75 p-7">
                                <p class="text-sm font-semibold uppercase tracking-[0.14em] text-cyan-300">About this article</p>
                                <div class="mt-4 space-y-3 text-sm leading-7 text-slate-300">
                                    <p><strong>Published:</strong> {{ $post->publishedLabel() }}</p>
                                    @if ($post->author)
                                        <p><strong>Author:</strong> {{ $post->author->name }}</p>
                                    @endif
                                    @if ($post->category)
                                        <p><strong>Category:</strong> {{ $post->category->name }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="rounded-3xl border border-white/10 bg-slate-900/75 p-7">
                                <p class="text-sm font-semibold uppercase tracking-[0.14em] text-cyan-300">FirePhage</p>
                                <p class="mt-4 text-sm leading-7 text-slate-300">FirePhage helps teams protect WordPress and WooCommerce sites with managed firewall controls, origin protection, uptime visibility, and clean onboarding without security noise.</p>
                                <x-marketing.auth-aware-link guest-label="Start Free" class="mt-5 inline-flex rounded-lg bg-cyan-500 px-4 py-2 text-sm font-medium text-slate-950 hover:bg-cyan-400" />
                            </div>

                            @if ($relatedServices->isNotEmpty())
                                <div class="rounded-3xl border border-white/10 bg-slate-900/75 p-7">
                                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-cyan-300">Related services</p>
                                    <div class="mt-5 space-y-5">
                                        @foreach ($relatedServices as $service)
                                            <article>
                                                <h2 class="text-base font-semibold leading-6 text-white">
                                                    <a href="{{ route('services.show', $service['slug']) }}" class="hover:text-cyan-200">{{ $service['label'] }}</a>
                                                </h2>
                                                <p class="mt-2 text-sm leading-6 text-slate-400">{{ $service['summary'] }}</p>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($relatedPosts->isNotEmpty())
                                <div class="rounded-3xl border border-white/10 bg-slate-900/75 p-7">
                                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-cyan-300">Related articles</p>
                                    <div class="mt-5 space-y-5">
                                        @foreach ($relatedPosts as $relatedPost)
                                            <article>
                                                <h2 class="text-base font-semibold leading-6 text-white">
                                                    <a href="{{ route('blog.show', $relatedPost) }}" class="hover:text-cyan-200">{{ $relatedPost->title }}</a>
                                                </h2>
                                                <p class="mt-2 text-sm leading-6 text-slate-400">{{ $relatedPost->publishedLabel() }}</p>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </aside>
                    </div>
                </main>

                <x-marketing.footer-variant-1 />
            </div>
        </div>
    </body>
</html>
