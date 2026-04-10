<?php

namespace App\Services\Marketing;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogImportService
{
    public function __construct(
        private readonly OpenAiBlogCoverImageService $coverImages,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{
     *   title:string,
     *   slug:string|null,
     *   meta_description:string|null,
     *   content_markdown:string|null,
     *   content_html:string|null,
     *   hero_image_url:string|null,
     *   public_url:string|null,
     *   created_at:string|null,
     *   generate_cover:bool|null,
     * }  $fields
     * @return array{post:BlogPost,created:bool}
     */
    public function import(array $payload, array $fields): array
    {
        $title = trim((string) $fields['title']);
        $slug = Str::slug((string) ($fields['slug'] ?: $title));
        $markdown = trim((string) ($fields['content_markdown'] ?? ''));

        if ($markdown === '') {
            $markdown = trim(strip_tags((string) ($fields['content_html'] ?? '')));
        }

        $markdown = $this->sanitizeImportedMarkdown($markdown);

        abort_if($slug === '' || $markdown === '', 422, 'The webhook payload must include a usable slug and article body.');

        $post = BlogPost::query()->firstOrNew(['slug' => $slug]);
        $wasRecentlyCreated = ! $post->exists;

        $post->fill([
            'blog_category_id' => $post->blog_category_id ?: $this->defaultCategoryId(),
            'authored_by' => $post->authored_by ?: $this->defaultAuthorId(),
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $fields['meta_description'] ?: $post->excerpt,
            'content_markdown' => $markdown,
            'cover_image_url' => $post->cover_image_url,
            'seo_title' => $post->seo_title ?: $this->defaultSeoTitle($title),
            'seo_description' => $fields['meta_description'] ?: $post->seo_description,
            'canonical_url' => $this->canonicalUrlForPayload($fields['public_url'], $slug),
            'og_image_url' => $post->og_image_url,
            'is_published' => true,
            'published_at' => $post->published_at ?: $this->publishedAtForPayload($fields['created_at']),
        ]);
        $post->save();

        $fallbackImage = $fields['hero_image_url'] ?? null;
        $generateCover = (bool) ($fields['generate_cover'] ?? true);

        if ($generateCover) {
            try {
                $generatedCover = $this->coverImages->generateForPost($post);

                $post->forceFill([
                    'cover_image_url' => $generatedCover['public_url'],
                    'og_image_url' => $generatedCover['public_url'],
                    'content_markdown' => $this->ensureTopCoverImage($post, $markdown, $generatedCover['public_url']),
                ])->save();
            } catch (\Throwable $exception) {
                Log::warning('Blog cover generation failed during import.', [
                    'post_id' => $post->id,
                    'slug' => $post->slug,
                    'message' => $exception->getMessage(),
                ]);

                if (is_string($fallbackImage) && trim($fallbackImage) !== '') {
                    $post->forceFill([
                        'cover_image_url' => $fallbackImage,
                        'og_image_url' => $fallbackImage,
                        'content_markdown' => $this->ensureTopCoverImage($post, $markdown, $fallbackImage),
                    ])->save();
                }
            }
        } elseif (is_string($fallbackImage) && trim($fallbackImage) !== '') {
            $post->forceFill([
                'cover_image_url' => $fallbackImage,
                'og_image_url' => $fallbackImage,
                'content_markdown' => $this->ensureTopCoverImage($post, $markdown, $fallbackImage),
            ])->save();
        }

        return [
            'post' => $post->fresh(),
            'created' => $wasRecentlyCreated,
        ];
    }

    private function defaultCategoryId(): ?int
    {
        return BlogCategory::query()
            ->where('slug', 'wordpress-security')
            ->value('id')
            ?? BlogCategory::query()->orderBy('id')->value('id');
    }

    private function defaultAuthorId(): ?int
    {
        return User::query()
            ->where('name', 'Nikola Jocic')
            ->orderBy('id')
            ->value('id')
            ?? User::query()->orderByDesc('is_super_admin')->orderBy('id')->value('id');
    }

    private function defaultSeoTitle(string $title): string
    {
        return Str::endsWith($title, ' | FirePhage') ? $title : "{$title} | FirePhage";
    }

    private function canonicalUrlForPayload(?string $publicUrl, string $slug): ?string
    {
        $publicUrl = trim((string) $publicUrl);

        if ($publicUrl === '') {
            return route('blog.show', ['post' => $slug]);
        }

        $host = parse_url($publicUrl, PHP_URL_HOST);
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        return $host === $appHost ? $publicUrl : route('blog.show', ['post' => $slug]);
    }

    private function publishedAtForPayload(?string $createdAt): Carbon
    {
        $createdAt = trim((string) $createdAt);

        if ($createdAt !== '') {
            return Carbon::parse($createdAt);
        }

        return now();
    }

    private function sanitizeImportedMarkdown(string $markdown): string
    {
        $cleaned = preg_replace(
            '/\n*\[Article generated by BabyLoveGrowth\]\(https?:\/\/www\.babylovegrowth\.ai\/?\)\s*$/iu',
            '',
            trim($markdown)
        );

        $cleaned = preg_replace(
            '/\n*Article generated by BabyLoveGrowth\s*$/iu',
            '',
            (string) $cleaned
        );

        $cleaned = preg_replace(
            '/\n*\[?\s*Enhanced by the Outrank app\s*\]?(?:\([^)]+\))?\s*$/iu',
            '',
            (string) $cleaned
        );

        $cleaned = preg_replace(
            '/\n*\[?\s*Enhanced by Outrank\s*\]?(?:\([^)]+\))?\s*$/iu',
            '',
            (string) $cleaned
        );

        $cleaned = preg_replace(
            '/\n*\*?\s*Enhanced by\s+\[the Outrank app\]\(https?:\/\/(?:www\.)?outrank\.so\/?\)\s*\*?\s*$/iu',
            '',
            (string) $cleaned
        );

        $cleaned = preg_replace(
            '/\n*\*?\s*Enhanced by\s+\[Outrank\]\(https?:\/\/(?:www\.)?outrank\.so\/?\)\s*\*?\s*$/iu',
            '',
            (string) $cleaned
        );

        $cleaned = preg_replace(
            '/^\s*!\[[^\]]*\]\((?:https?:\/\/|\/)[^)]+\)\s*$/imu',
            '',
            (string) $cleaned
        );

        $cleaned = preg_replace(
            '/^\s*<p>\s*<img[^>]+>\s*<\/p>\s*$/imu',
            '',
            (string) $cleaned
        );

        $cleaned = preg_replace(
            '/^\s*<img[^>]+>\s*$/imu',
            '',
            (string) $cleaned
        );

        return trim((string) $cleaned);
    }

    private function ensureTopCoverImage(BlogPost $post, string $markdown, string $imageUrl): string
    {
        $body = ltrim($markdown);
        $imageBlock = sprintf("![%s](%s)\n\n", $post->title, $imageUrl);

        return $imageBlock.$body;
    }
}
