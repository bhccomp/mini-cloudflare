<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class BlogController extends Controller
{
    public function index(): View
    {
        $featuredPost = BlogPost::query()
            ->published()
            ->with('category')
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->first();

        $posts = BlogPost::query()
            ->published()
            ->with('category')
            ->when($featuredPost, fn ($query) => $query->whereKeyNot($featuredPost->id))
            ->orderByDesc('published_at')
            ->paginate(9);

        $categories = BlogCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('marketing.blog.index', [
            'featuredPost' => $featuredPost,
            'posts' => $posts,
            'categories' => $categories,
            'serviceLinks' => $this->serviceLinks(),
        ]);
    }

    public function show(BlogPost $post): View
    {
        abort_unless($post->is_published && ($post->published_at === null || $post->published_at->isPast()), 404);

        $post->loadMissing('category', 'author');

        $relatedPosts = BlogPost::query()
            ->published()
            ->with('category')
            ->when($post->blog_category_id, fn ($query) => $query->where('blog_category_id', $post->blog_category_id))
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('marketing.blog.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'relatedServices' => $this->relatedServicesForPost($post),
        ]);
    }

    private function serviceLinks(): Collection
    {
        return collect(config('marketing-services', []))
            ->only(['waf', 'bot-protection', 'wordpress-plugin', 'ddos-protection'])
            ->map(fn (array $service, string $slug): array => [
                'slug' => $slug,
                'label' => $service['nav_label'],
                'summary' => $service['summary'],
            ])
            ->values();
    }

    private function relatedServicesForPost(BlogPost $post): Collection
    {
        $haystack = strtolower(trim(implode(' ', array_filter([
            $post->title,
            $post->excerpt,
            $post->content_markdown,
            $post->category?->name,
        ]))));

        $serviceMap = collect([
            'waf' => ['waf', 'firewall', 'origin', 'edge security'],
            'bot-protection' => ['bot', 'bots', 'scraping', 'brute force', 'login abuse', 'woocommerce'],
            'ddos-protection' => ['ddos', 'surge', 'traffic spike', 'under attack'],
            'cache' => ['cache', 'caching', 'origin offload', 'performance'],
            'cdn' => ['cdn', 'delivery', 'latency'],
            'wordpress-plugin' => ['wordpress', 'plugin', 'malware', 'checksum', 'health'],
            'uptime-monitor' => ['uptime', 'availability', 'downtime', 'monitoring'],
        ]);

        $matched = $serviceMap
            ->filter(fn (array $keywords): bool => collect($keywords)->contains(fn (string $keyword): bool => str_contains($haystack, strtolower($keyword))))
            ->keys();

        if ($matched->isEmpty()) {
            $matched = collect(['waf', 'bot-protection', 'wordpress-plugin']);
        }

        return collect(config('marketing-services', []))
            ->only($matched->all())
            ->map(fn (array $service, string $slug): array => [
                'slug' => $slug,
                'label' => $service['nav_label'],
                'summary' => $service['summary'],
            ])
            ->values()
            ->take(3);
    }
}
