<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BlogPost extends Model
{
    protected $fillable = [
        'blog_category_id',
        'authored_by',
        'title',
        'slug',
        'excerpt',
        'content_markdown',
        'cover_image_url',
        'seo_title',
        'seo_description',
        'canonical_url',
        'og_image_url',
        'is_featured',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function seoTitle(): string
    {
        return trim((string) ($this->seo_title ?: $this->title));
    }

    public function seoDescription(): string
    {
        return trim((string) ($this->seo_description ?: $this->excerpt ?: 'FirePhage insights about WordPress protection, bot attacks, firewalls, and practical website security.'));
    }

    public function readingTimeMinutes(): int
    {
        $wordCount = str_word_count(strip_tags((string) $this->content_markdown));

        return max(1, (int) ceil($wordCount / 220));
    }

    public function publishedLabel(): string
    {
        return $this->published_at instanceof Carbon
            ? $this->published_at->format('F j, Y')
            : $this->created_at?->format('F j, Y') ?? 'Draft';
    }
}
