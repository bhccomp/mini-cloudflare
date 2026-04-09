<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\Seo\LlmsTxtService;
use App\Services\Seo\SitemapService;
use App\Support\MarketingSeo;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function llms(): Response
    {
        $content = app(LlmsTxtService::class)->render();

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

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
        $urls = app(SitemapService::class)->includedUrls();

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
