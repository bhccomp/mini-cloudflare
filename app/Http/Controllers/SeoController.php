<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Sitemap: ' . route('seo.sitemap'),
            '',
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('services.index'), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => route('blog.index'), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('contact'), 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => route('terms'), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('privacy'), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('cookies'), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('refund-policy'), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('acceptable-use'), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ]);

        $urls = $urls->merge(
            collect(config('marketing-services', []))->keys()->map(fn (string $service) => [
                'loc' => route('services.show', $service),
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
                    'loc' => route('blog.show', $post),
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
