<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;

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
        ]);
    }
}
