<?php

namespace App\Support;

use App\Models\BlogPost;
use Illuminate\Support\Str;

class MarketingSeo
{
    public static function preferredUrl(?string $url = null): string
    {
        $candidate = $url ?: request()->fullUrl();
        $preferred = parse_url((string) config('app.url'), PHP_URL_HOST);
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        if (blank($candidate)) {
            return rtrim((string) config('app.url'), '/');
        }

        if (! Str::startsWith($candidate, ['http://', 'https://'])) {
            $candidate = url($candidate);
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return rtrim((string) config('app.url'), '/');
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return sprintf(
            '%s://%s%s%s',
            $scheme,
            $preferred ?: ($parts['host'] ?? request()->getHost()),
            $path === '/' ? '' : $path,
            $query,
        );
    }

    public static function homepageTitle(): string
    {
        return (string) config('marketing-seo.home.title', 'WordPress and WooCommerce Protection | FirePhage');
    }

    public static function homepageDescription(): string
    {
        return (string) config('marketing-seo.home.description', 'Protect WordPress and WooCommerce sites with WAF, bot filtering, origin shielding, monitoring, and clear operational visibility from one dashboard.');
    }

    public static function blogIndexTitle(): string
    {
        return (string) config('marketing-seo.blog_index.title', 'FirePhage Blog | WordPress Security and Bot Protection');
    }

    public static function blogIndexDescription(): string
    {
        return (string) config('marketing-seo.blog_index.description', 'Practical articles about WordPress security, WooCommerce bot pressure, origin protection, and safer DNS cutovers.');
    }

    public static function blogTitle(BlogPost $post): string
    {
        $override = config('marketing-seo.posts.'.$post->slug.'.title');

        if (filled($override)) {
            return trim((string) $override);
        }

        if (filled($post->seo_title)) {
            return trim((string) $post->seo_title);
        }

        $title = trim((string) $post->title);

        if (Str::contains($title, 'FirePhage')) {
            return $title;
        }

        return Str::length($title) <= 58
            ? $title.' | FirePhage'
            : $title;
    }

    public static function blogDescription(BlogPost $post): string
    {
        $override = config('marketing-seo.posts.'.$post->slug.'.description');

        if (filled($override)) {
            return trim((string) $override);
        }

        if (filled($post->seo_description)) {
            return self::cleanDescription((string) $post->seo_description);
        }

        if (filled($post->excerpt)) {
            return self::cleanDescription((string) $post->excerpt);
        }

        return self::descriptionFromContent((string) $post->content_markdown);
    }

    public static function blogOgImageAlt(BlogPost $post): string
    {
        $override = config('marketing-seo.posts.'.$post->slug.'.og_image_alt');

        if (filled($override)) {
            return trim((string) $override);
        }

        return trim($post->title).' article preview';
    }

    public static function descriptionFromContent(string $content, int $limit = 155): string
    {
        $text = self::plainText($content);

        if ($text === '') {
            return 'FirePhage insights about WordPress security, bot abuse, origin protection, and practical website operations.';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [];
        $description = '';

        foreach ($sentences as $sentence) {
            $candidate = trim($description.' '.$sentence);

            if ($candidate === '') {
                continue;
            }

            if (Str::length($candidate) > $limit) {
                break;
            }

            $description = $candidate;
        }

        if ($description !== '') {
            return $description;
        }

        return self::truncateClean($text, $limit);
    }

    public static function cleanDescription(string $text, int $limit = 155): string
    {
        return self::truncateClean(self::plainText($text), $limit);
    }

    public static function plainText(string $text): string
    {
        $text = strip_tags(Str::markdown($text));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return trim($text);
    }

    public static function truncateClean(string $text, int $limit = 155): string
    {
        $text = trim($text);

        if ($text === '' || Str::length($text) <= $limit) {
            return $text;
        }

        $snippet = Str::substr($text, 0, $limit + 1);
        $lastBreak = max(
            (int) strrpos($snippet, '. '),
            (int) strrpos($snippet, '; '),
            (int) strrpos($snippet, ', '),
            (int) strrpos($snippet, ' ')
        );

        if ($lastBreak > (int) floor($limit * 0.6)) {
            $snippet = Str::substr($snippet, 0, $lastBreak);
        } else {
            $snippet = Str::substr($snippet, 0, $limit);
        }

        return rtrim($snippet, " \t\n\r\0\x0B,;:-").'.';
    }
}
