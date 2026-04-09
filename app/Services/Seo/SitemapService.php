<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\SystemSetting;
use App\Support\MarketingSeo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Route as LaravelRoute;

class SitemapService
{
    public const SETTING_KEY = 'sitemap';

    /**
     * @return Collection<int, array{loc:string,lastmod:?string,changefreq:string,priority:string,source:string,label:string}>
     */
    public function includedUrls(): Collection
    {
        $excluded = $this->excludedUrls();

        return $this->detectedUrls()
            ->reject(fn (array $url): bool => in_array($url['loc'], $excluded, true))
            ->values();
    }

    /**
     * @return Collection<int, array{loc:string,lastmod:?string,changefreq:string,priority:string,source:string,label:string}>
     */
    public function detectedUrls(): Collection
    {
        return collect()
            ->merge($this->staticRouteUrls())
            ->merge($this->serviceUrls())
            ->merge($this->blogUrls())
            ->unique('loc')
            ->values();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function checkboxOptions(): array
    {
        return $this->detectedUrls()
            ->mapWithKeys(function (array $url): array {
                $source = match ($url['source']) {
                    'static' => 'Static',
                    'services' => 'Service',
                    'blog' => 'Blog',
                    default => ucfirst((string) $url['source']),
                };

                return [
                    $url['loc'] => '[' . $source . '] ' . $url['label'],
                ];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    public function selectedUrls(): array
    {
        $excluded = $this->excludedUrls();

        return $this->detectedUrls()
            ->pluck('loc')
            ->reject(fn (string $loc): bool => in_array($loc, $excluded, true))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $selectedUrls
     */
    public function saveSelectedUrls(array $selectedUrls): void
    {
        $selected = array_values(array_unique(array_filter($selectedUrls, fn ($value): bool => is_string($value) && $value !== '')));
        $all = $this->detectedUrls()->pluck('loc')->values()->all();
        $excluded = array_values(array_diff($all, $selected));

        $setting = SystemSetting::query()->firstOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => [],
                'is_encrypted' => false,
                'description' => 'Excluded URLs for sitemap.xml generation',
            ]
        );

        $setting->forceFill([
            'value' => ['excluded_urls' => $excluded],
            'is_encrypted' => false,
            'description' => 'Excluded URLs for sitemap.xml generation',
        ])->save();
    }

    /**
     * @return list<string>
     */
    public function excludedUrls(): array
    {
        $value = SystemSetting::query()->where('key', self::SETTING_KEY)->value('value');
        $excluded = is_array($value) ? ($value['excluded_urls'] ?? []) : [];

        return collect(is_array($excluded) ? $excluded : [])
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{loc:string,lastmod:?string,changefreq:string,priority:string,source:string,label:string}>
     */
    protected function staticRouteUrls(): Collection
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => $this->isEligibleStaticRoute($route))
            ->map(function (LaravelRoute $route): array {
                $name = (string) $route->getName();
                $loc = MarketingSeo::preferredUrl(route($name));

                return [
                    'loc' => $loc,
                    'lastmod' => null,
                    'changefreq' => $this->routeChangefreq($name),
                    'priority' => $this->routePriority($name),
                    'source' => 'static',
                    'label' => $this->routeLabel($route),
                ];
            })
            ->sortBy('loc')
            ->values();
    }

    /**
     * @return Collection<int, array{loc:string,lastmod:?string,changefreq:string,priority:string,source:string,label:string}>
     */
    protected function serviceUrls(): Collection
    {
        return collect(config('marketing-services', []))
            ->keys()
            ->map(function (string $service): array {
                return [
                    'loc' => MarketingSeo::preferredUrl(route('services.show', $service)),
                    'lastmod' => null,
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                    'source' => 'services',
                    'label' => '/services/' . $service,
                ];
            })
            ->sortBy('loc')
            ->values();
    }

    /**
     * @return Collection<int, array{loc:string,lastmod:?string,changefreq:string,priority:string,source:string,label:string}>
     */
    protected function blogUrls(): Collection
    {
        return BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->get()
            ->map(function (BlogPost $post): array {
                return [
                    'loc' => MarketingSeo::preferredUrl($post->canonical_url ?: route('blog.show', $post)),
                    'lastmod' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                    'source' => 'blog',
                    'label' => trim((string) $post->title),
                ];
            })
            ->values();
    }

    protected function isEligibleStaticRoute(LaravelRoute $route): bool
    {
        $name = (string) $route->getName();
        $uri = '/' . ltrim($route->uri(), '/');
        $methods = $route->methods();

        if ($name === '' || str_starts_with($name, 'generated::') || ! in_array('GET', $methods, true)) {
            return false;
        }

        if ($route->parameterNames() !== []) {
            return false;
        }

        if ($this->excludedRouteName($name)) {
            return false;
        }

        return ! $this->excludedUri($uri);
    }

    protected function excludedRouteName(string $name): bool
    {
        if (in_array($name, [
            'login',
            'register',
            'early-access',
            'billing.checkout.complete',
            'billing.checkout.cancelled',
            'home.blue',
            'about.founder-photo',
        ], true)) {
            return true;
        }

        foreach (['seo.', 'admin.', 'app.', 'verification.', 'webhooks.', 'stripe.', 'wordpress.', 'auth.'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function excludedUri(string $uri): bool
    {
        foreach ([
            '/app',
            '/admin',
            '/api',
            '/wordpress',
            '/stripe',
            '/webhooks',
            '/auth',
            '/billing/checkout',
            '/docs',
            '/early-access',
            '/livewire',
            '/llms.txt',
            '/robots.txt',
            '/sitemap.xml',
            '/up',
        ] as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function routeChangefreq(string $name): string
    {
        return match ($name) {
            'home', 'services.index', 'blog.index' => 'weekly',
            'terms', 'privacy', 'cookies', 'refund-policy', 'acceptable-use' => 'yearly',
            default => 'monthly',
        };
    }

    protected function routePriority(string $name): string
    {
        return match ($name) {
            'home' => '1.0',
            'services.index' => '0.9',
            'blog.index' => '0.8',
            'about' => '0.7',
            'contact' => '0.6',
            'terms', 'privacy', 'cookies', 'refund-policy', 'acceptable-use' => '0.3',
            default => '0.5',
        };
    }

    protected function routeLabel(LaravelRoute $route): string
    {
        $name = (string) $route->getName();
        $uri = '/' . trim($route->uri(), '/');

        return match ($name) {
            'home' => '/',
            default => $uri === '/' ? '/' : $uri,
        };
    }
}
