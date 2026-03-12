<?php

namespace App\Services\WordPress;

use App\Models\WordPressMaliciousDomain;

class WordPressMaliciousDomainFeedService
{
    public const RESOURCE_PATH = 'resources/wordpress-malicious-domains/romainmarcoux-malicious-domains.txt';

    /**
     * @return array<int, string>
     */
    public function domains(): array
    {
        $databaseDomains = WordPressMaliciousDomain::query()
            ->where('status', 'active')
            ->orderBy('domain')
            ->pluck('domain')
            ->filter(fn ($domain): bool => is_string($domain) && $domain !== '')
            ->map(fn (string $domain): string => strtolower(trim($domain)))
            ->unique()
            ->values()
            ->all();

        if ($databaseDomains !== []) {
            return $databaseDomains;
        }

        $path = base_path(self::RESOURCE_PATH);

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($lines)) {
            return [];
        }

        $domains = [];

        foreach ($lines as $line) {
            $domain = strtolower(trim((string) $line));

            if ($domain === '' || preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,24}$/', $domain) !== 1) {
                continue;
            }

            $domains[$domain] = true;
        }

        ksort($domains, SORT_STRING);

        return array_keys($domains);
    }
}
