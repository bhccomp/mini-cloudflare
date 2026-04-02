<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\MarketingSeo;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $content = implode("\n", [
            '# FirePhage robots.txt',
            '# Search-focused AI crawlers',
            'User-agent: OAI-SearchBot',
            'Allow: /',
            '',
            'User-agent: Claude-SearchBot',
            'Allow: /',
            '',
            'User-agent: PerplexityBot',
            'Allow: /',
            '',
            '# Training crawlers blocked by default',
            'User-agent: GPTBot',
            'Disallow: /',
            '',
            'User-agent: ClaudeBot',
            'Disallow: /',
            '',
            'User-agent: *',
            'Allow: /',
            'Sitemap: ' . MarketingSeo::preferredUrl(route('seo.sitemap')),
            '',
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => MarketingSeo::preferredUrl(route('home')), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => MarketingSeo::preferredUrl(route('services.index')), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => MarketingSeo::preferredUrl(route('blog.index')), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => MarketingSeo::preferredUrl(route('contact')), 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => MarketingSeo::preferredUrl(route('terms')), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => MarketingSeo::preferredUrl(route('privacy')), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => MarketingSeo::preferredUrl(route('cookies')), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => MarketingSeo::preferredUrl(route('refund-policy')), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => MarketingSeo::preferredUrl(route('acceptable-use')), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ]);

        $urls = $urls->merge(
            collect(config('marketing-services', []))->keys()->map(fn (string $service) => [
                'loc' => MarketingSeo::preferredUrl(route('services.show', $service)),
                'lastmod' => null,
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ])
        )->merge(
            BlogPost::query()
                ->published()
                ->orderByDesc('published_at')
                ->get()
                ->map(fn (BlogPost $post) => [
                    'loc' => MarketingSeo::preferredUrl($post->canonical_url ?: route('blog.show', $post)),
                    'lastmod' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ])
        );

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
