<?php

namespace App\Services\WordPress;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class WordPressReferenceFileService
{
    private const CACHE_TTL_SECONDS = 86400 * 14;

    /**
     * @return array<string, mixed>
     */
    public function getReferenceFile(string $type, ?string $slug, string $version, string $path): array
    {
        $normalizedType = $this->normalizeType($type);
        $normalizedSlug = $normalizedType === 'core' ? null : $this->normalizeSlug((string) $slug);
        $normalizedVersion = trim($version);
        $normalizedPath = $this->normalizePath($path);

        if ($normalizedVersion === '') {
            throw new RuntimeException('A package version is required.');
        }

        $cacheKey = implode(':', [
            'wp_reference_file',
            $normalizedType,
            $normalizedSlug ?? '_',
            $normalizedVersion,
            md5($normalizedPath),
        ]);

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($normalizedType, $normalizedSlug, $normalizedVersion, $normalizedPath): array {
            return $this->fetchFromWordPress($normalizedType, $normalizedSlug, $normalizedVersion, $normalizedPath);
        });

        $payload['cached'] = true;

        return $payload;
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));

        if (! in_array($normalized, ['core', 'plugin', 'theme'], true)) {
            throw new RuntimeException('Unsupported reference package type.');
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

    private function normalizePath(string $path): string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        if ($normalized === '' || str_contains($normalized, '../') || str_contains($normalized, '..\\')) {
            throw new RuntimeException('Invalid package file path.');
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFromWordPress(string $type, ?string $slug, string $version, string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is not available on the FirePhage server.');
        }

        [$url, $prefix] = $this->packageLocation($type, $slug, $version);
        $response = Http::timeout(20)
            ->withHeaders([
                'User-Agent' => 'FirePhage checksum compare/1.0',
            ])
            ->retry(2, 250, throw: false)
            ->get($url);

        if (! $response->successful()) {
            if ($type === 'plugin' && $response->status() === 404 && $slug !== null) {
                $fallbackUrl = $this->resolvePluginVersionDownloadUrl($slug, $version);

                if ($fallbackUrl !== null) {
                    $response = Http::timeout(20)
                        ->withHeaders([
                            'User-Agent' => 'FirePhage checksum compare/1.0',
                        ])
                        ->retry(2, 250, throw: false)
                        ->get($fallbackUrl);
                }
            }

            if (! $response->successful()) {
                throw new RuntimeException($this->downloadFailureMessage($type, $slug, $version, $response->status()));
            }
        }

        if (! $response->successful()) {
            throw new RuntimeException('Unable to download the official package from WordPress.org.');
        }

        $zipBody = $response->body();

        if ($zipBody === '') {
            throw new RuntimeException('Unable to download the official package from WordPress.org.');
        }

        $tempZip = tempnam(sys_get_temp_dir(), 'fp-ref-');

        if ($tempZip === false) {
            throw new RuntimeException('Unable to create a temporary package file.');
        }

        file_put_contents($tempZip, $zipBody);

        try {
            $content = $this->extractReferenceFile($tempZip, $prefix, $path);
        } finally {
            @unlink($tempZip);
        }

        return [
            'type' => $type,
            'slug' => $slug,
            'version' => $version,
            'path' => $path,
            'content' => $content,
            'cached' => false,
            'source' => 'wordpress_org',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function packageLocation(string $type, ?string $slug, string $version): array
    {
        if ($type === 'core') {
            return [
                sprintf('https://downloads.wordpress.org/release/wordpress-%s.zip', rawurlencode($version)),
                'wordpress/',
            ];
        }

        if ($slug === null) {
            throw new RuntimeException('A package slug is required.');
        }

        return [
            sprintf(
                'https://downloads.wordpress.org/%s/%s.%s.zip',
                $type,
                rawurlencode($slug),
                rawurlencode($version),
            ),
            trim($slug, '/') . '/',
        ];
    }

    private function extractReferenceFile(string $zipPath, string $prefix, string $path): string
    {
        $archive = new ZipArchive();

        if ($archive->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open the official package archive.');
        }

        try {
            $entryName = $this->locateEntry($archive, $prefix, $path);

            if ($entryName === null) {
                throw new RuntimeException('The requested reference file was not found in the official package.');
            }

            $content = $archive->getFromName($entryName);

            if (! is_string($content)) {
                throw new RuntimeException('The requested reference file could not be read from the official package.');
            }

            return $content;
        } finally {
            $archive->close();
        }
    }

    private function locateEntry(ZipArchive $archive, string $prefix, string $path): ?string
    {
        $expected = $prefix . ltrim($path, '/');

        if ($archive->locateName($expected, ZipArchive::FL_NOCASE) !== false) {
            return $expected;
        }

        $needle = '/' . ltrim($path, '/');

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);
            $name = is_array($stat) ? ($stat['name'] ?? null) : null;

            if (! is_string($name)) {
                continue;
            }

            if (str_ends_with($name, $needle)) {
                return $name;
            }
        }

        return null;
    }

    private function resolvePluginVersionDownloadUrl(string $slug, string $version): ?string
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->withHeaders([
                'User-Agent' => 'FirePhage checksum compare/1.0',
            ])
            ->retry(2, 250, throw: false)
            ->get('https://api.wordpress.org/plugins/info/1.2/', [
                'action' => 'plugin_information',
                'request' => [
                    'slug' => $slug,
                    'fields' => [
                        'versions' => true,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        $versions = Arr::get($payload, 'versions', []);

        if (! is_array($versions)) {
            return null;
        }

        $url = $versions[$version] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function downloadFailureMessage(string $type, ?string $slug, string $version, int $status): string
    {
        if ($type === 'plugin' && $slug !== null) {
            if ($status === 404) {
                return sprintf('The official WordPress.org plugin package for %s version %s is not available for compare or restore.', $slug, $version);
            }

            return sprintf('Unable to download the official WordPress.org plugin package for %s version %s.', $slug, $version);
        }

        if ($type === 'theme' && $slug !== null) {
            if ($status === 404) {
                return sprintf('The official WordPress.org theme package for %s version %s is not available for compare or restore.', $slug, $version);
            }

            return sprintf('Unable to download the official WordPress.org theme package for %s version %s.', $slug, $version);
        }

        if ($type === 'core') {
            if ($status === 404) {
                return sprintf('The official WordPress core package for version %s is not available for compare or restore.', $version);
            }

            return sprintf('Unable to download the official WordPress core package for version %s.', $version);
        }

        return 'Unable to download the official package from WordPress.org.';
    }
}
