<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_shows_only_published_posts(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'WordPress Security',
            'slug' => 'wordpress-security-test',
            'is_active' => true,
        ]);

        $published = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'How to protect wp-login.php without breaking users',
            'slug' => 'protect-wp-login',
            'excerpt' => 'A practical guide.',
            'content_markdown' => '# Hello world',
            'is_published' => true,
            'published_at' => now()->subHour(),
        ]);

        BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Draft article',
            'slug' => 'draft-article',
            'excerpt' => 'This should not appear.',
            'content_markdown' => '# Draft',
            'is_published' => false,
        ]);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertSee($published->title);
        $response->assertDontSee('Draft article');
    }

    public function test_blog_show_renders_published_post_and_hides_draft(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'Bot attacks against WooCommerce checkouts',
            'slug' => 'bot-attacks-woocommerce-checkouts',
            'excerpt' => 'How noisy checkout abuse usually looks.',
            'content_markdown' => "## Section\n\nHelpful content.",
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee('Helpful content.');

        $draft = BlogPost::query()->create([
            'title' => 'Draft only',
            'slug' => 'draft-only',
            'content_markdown' => '# Hidden',
            'is_published' => false,
        ]);

        $this->get(route('blog.show', $draft))
            ->assertNotFound();
    }
}
