<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_references_the_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee(route('seo.sitemap'), false);
    }

    public function test_sitemap_includes_public_marketing_routes_and_published_blog_posts_only(): void
    {
        $published = BlogPost::query()->create([
            'title' => 'Published SEO post',
            'slug' => 'published-seo-post',
            'content_markdown' => '# Published',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        BlogPost::query()->create([
            'title' => 'Draft SEO post',
            'slug' => 'draft-seo-post',
            'content_markdown' => '# Draft',
            'is_published' => false,
        ]);

        $response = $this->get(route('seo.sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(route('services.index'), false);
        $response->assertSee(route('blog.show', $published), false);
        $response->assertDontSee('draft-seo-post', false);
        $response->assertSee(route('services.show', 'waf'), false);
    }
}
