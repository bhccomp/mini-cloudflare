<?php

namespace App\Services\WordPress;

use App\Models\PackageChecksumCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressChecksumCacheService
{
    private const SUCCESS_TTL_DAYS = 30;

    private const FAILURE_TTL_HOURS = 6;

    /**
     * @return array<string, mixed>
     */
    public function getChecksums(string $type, string $slug, string $version): array
    {
        $normalizedType = $this->normalizeType($type);
        $normalizedSlug = $this->normalizeSlug($slug);
        $normalizedVersion = trim($version);

        $cache = PackageChecksumCache::query()->firstOrNew([
            'type' => $normalizedType,
            'slug' => $normalizedSlug,
            'version' => $normalizedVersion,
        ]);

        if (is_array($cache->checksums) && $cache->checksums !== [] && $cache->expires_at?->isFuture()) {
            return [
                'type' => $normalizedType,
                'slug' => $normalizedSlug,
                'version' => $normalizedVersion,
                'checksums' => $cache->checksums,
                'cached' => true,
                'stale' => false,
                'fetched_at' => optional($cache->fetched_at)?->toIso8601String(),
                'expires_at' => optional($cache->expires_at)?->toIso8601String(),
                'source' => 'firephage_cache',
            ];
        }

        try {
            $checksums = $this->fetchFromWordPress($normalizedType, $normalizedSlug, $normalizedVersion);

            $cache->fill([
                'checksums' => $checksums,
                'fetched_at' => now(),
                'expires_at' => now()->addDays(self::SUCCESS_TTL_DAYS),
                'last_error' => null,
                'last_error_at' => null,
            ])->save();

            return [
                'type' => $normalizedType,
                'slug' => $normalizedSlug,
                'version' => $normalizedVersion,
                'checksums' => $checksums,
                'cached' => false,
                'stale' => false,
                'fetched_at' => optional($cache->fetched_at)?->toIso8601String(),
                'expires_at' => optional($cache->expires_at)?->toIso8601String(),
                'source' => 'wordpress_org',
            ];
        } catch (RuntimeException $exception) {
            $cache->fill([
                'last_error' => $exception->getMessage(),
                'last_error_at' => now(),
                'expires_at' => now()->addHours(self::FAILURE_TTL_HOURS),
            ])->save();

            if (is_array($cache->checksums) && $cache->checksums !== []) {
                return [
                    'type' => $normalizedType,
                    'slug' => $normalizedSlug,
                    'version' => $normalizedVersion,
                    'checksums' => $cache->checksums,
                    'cached' => true,
                    'stale' => true,
                    'fetched_at' => optional($cache->fetched_at)?->toIso8601String(),
                    'expires_at' => optional($cache->expires_at)?->toIso8601String(),
                    'source' => 'firephage_cache_stale',
                    'warning' => $exception->getMessage(),
                ];
            }

            throw $exception;
        }
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));

        if (! in_array($normalized, ['plugin', 'theme'], true)) {
            throw new RuntimeException('Unsupported checksum package type.');
        }

        return $normalized;
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));

        if ($normalized === '' || preg_match('/^[a-z0-9][a-z0-9\-_.]*$/', $normalized) !== 1) {
            throw new RuntimeException('Invalid package slug.');
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function fetchFromWordPress(string $type, string $slug, string $version): array
    {
        $response = $type === 'plugin'
            ? Http::acceptJson()->timeout(10)->get("https://downloads.wordpress.org/plugin-checksums/{$slug}/{$version}.json")
            : Http::acceptJson()->timeout(10)->get('https://api.wordpress.org/themes/checksums/1.0/', [
                'slug' => $slug,
                'version' => $version,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to fetch package checksums from WordPress.org.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('WordPress.org returned an invalid checksum response.');
        }

        $rawChecksums = $type === 'plugin'
            ? Arr::get($payload, 'files', [])
            : Arr::get($payload, 'checksums', []);

        if (! is_array($rawChecksums) || $rawChecksums === []) {
            throw new RuntimeException('WordPress.org did not return any checksums for this package version.');
        }

        $checksums = [];

        foreach ($rawChecksums as $path => $value) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            if ($type === 'plugin') {
                if (! is_array($value)) {
                    continue;
                }

                $checksum = $value['sha256'] ?? $value['md5'] ?? null;
            } else {
                $checksum = $value;
            }

            if (! is_string($checksum) || $checksum === '') {
                continue;
            }

            $checksums[ltrim($path, '/')] = strtolower($checksum);
        }

        if ($checksums === []) {
            throw new RuntimeException('WordPress.org did not return any usable checksums for this package version.');
        }

        return $checksums;
    }
}
