<?php

namespace App\Services\WordPress;

use App\Models\WordPressMaliciousDomain;
use App\Models\WordPressSignatureSample;
use Illuminate\Support\Carbon;

class WordPressMaliciousDomainService
{
    /**
     * @return array{imported:int,updated:int,skipped:int}
     */
    public function importFromFile(string $path, string $source = 'external_feed'): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $buffer = [];
        $skipped = 0;
        $updated = 0;
        $imported = 0;

        while (($line = fgets($handle)) !== false) {
            $domain = strtolower(trim((string) $line));

            if ($domain === '' || preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,24}$/', $domain) !== 1) {
                $skipped++;
                continue;
            }

            $buffer[$domain] = [
                'domain' => $domain,
                'status' => 'active',
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($buffer) < 2000) {
                continue;
            }

            [$chunkImported, $chunkUpdated] = $this->upsertChunk(array_values($buffer));
            $imported += $chunkImported;
            $updated += $chunkUpdated;
            $buffer = [];
        }

        fclose($handle);

        if ($buffer !== []) {
            [$chunkImported, $chunkUpdated] = $this->upsertChunk(array_values($buffer));
            $imported += $chunkImported;
            $updated += $chunkUpdated;
        }

        return compact('imported', 'updated', 'skipped');
    }

    /**
     * @param  array<int, array{domain:string,status:string,source:string,created_at:mixed,updated_at:mixed}>  $rows
     * @return array{0:int,1:int}
     */
    private function upsertChunk(array $rows): array
    {
        if ($rows === []) {
            return [0, 0];
        }

        $existing = WordPressMaliciousDomain::query()
            ->whereIn('domain', array_column($rows, 'domain'))
            ->pluck('domain')
            ->all();
        $existingLookup = array_fill_keys(array_map('strval', $existing), true);
        $updated = 0;
        $imported = 0;

        foreach ($rows as $row) {
            if (isset($existingLookup[$row['domain']])) {
                $updated++;
            } else {
                $imported++;
            }
        }

        WordPressMaliciousDomain::query()->upsert(
            $rows,
            ['domain'],
            ['status', 'source', 'updated_at']
        );

        return [$imported, $updated];
    }

    /**
     * @return array<string, mixed>
     */
    public function testDomain(WordPressMaliciousDomain $domain): array
    {
        $results = $this->testDomainSet([$domain->id => $domain->domain], true);

        return $results[$domain->id] ?? [
            'summary' => [
                'sample_count' => (int) WordPressSignatureSample::query()->count(),
                'malware_hits' => 0,
                'clean_hits' => 0,
                'false_positive_hits' => 0,
            ],
            'outcome' => 'no_hit',
            'matched_samples' => [],
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<WordPressMaliciousDomain>  $domains
     * @return array{tested:int,matched:int,total_malware_hits:int}
     */
    public function testSelectedDomains(iterable $domains): array
    {
        $domainMap = [];

        foreach ($domains as $domain) {
            if (! $domain instanceof WordPressMaliciousDomain) {
                continue;
            }

            $domainMap[$domain->id] = $domain->domain;
        }

        if ($domainMap === []) {
            return ['tested' => 0, 'matched' => 0, 'total_malware_hits' => 0];
        }

        $results = $this->testDomainSet($domainMap, true);

        return [
            'tested' => count($domainMap),
            'matched' => count(array_filter($results, fn (array $result): bool => (int) ($result['summary']['malware_hits'] ?? 0) > 0)),
            'total_malware_hits' => array_sum(array_map(fn (array $result): int => (int) ($result['summary']['malware_hits'] ?? 0), $results)),
        ];
    }

    /**
     * @return array{tested:int,matched:int,total_malware_hits:int}
     */
    public function testAllActiveDomains(): array
    {
        $domainMap = WordPressMaliciousDomain::query()
            ->where('status', 'active')
            ->pluck('domain', 'id')
            ->map(fn (string $domain): string => strtolower(trim($domain)))
            ->all();

        if ($domainMap === []) {
            return ['tested' => 0, 'matched' => 0, 'total_malware_hits' => 0];
        }

        $sampleCount = (int) WordPressSignatureSample::query()->count();
        $domainLookup = [];

        foreach ($domainMap as $id => $domain) {
            $domainLookup[strtolower(trim((string) $domain))] = (int) $id;
        }

        $matched = [];
        $totalMalwareHits = 0;
        $now = Carbon::now();

        foreach (WordPressSignatureSample::query()->orderBy('id')->cursor() as $sample) {
            $content = $this->sampleContent($sample);

            if ($content === '') {
                continue;
            }

            foreach ($this->extractDomains($content) as $domain) {
                $id = $domainLookup[$domain] ?? null;

                if ($id === null) {
                    continue;
                }

                if (! isset($matched[$id])) {
                    $matched[$id] = [
                        'summary' => [
                            'sample_count' => $sampleCount,
                            'malware_hits' => 0,
                            'clean_hits' => 0,
                            'false_positive_hits' => 0,
                        ],
                        'outcome' => 'no_hit',
                        'matched_samples' => [],
                        'tested_at' => $now->toIso8601String(),
                    ];
                }

                if ($sample->sample_type === 'malware') {
                    $matched[$id]['summary']['malware_hits']++;
                    $totalMalwareHits++;
                } elseif ($sample->sample_type === 'clean') {
                    $matched[$id]['summary']['clean_hits']++;
                } else {
                    $matched[$id]['summary']['false_positive_hits']++;
                }

                if (count($matched[$id]['matched_samples']) < 25) {
                    $matched[$id]['matched_samples'][] = [
                        'sample' => $sample->name,
                        'type' => $sample->sample_type,
                        'family' => $sample->family,
                        'original_filename' => $sample->original_filename,
                    ];
                }
            }
        }

        $updates = [];

        foreach ($matched as $id => $result) {
            $result['outcome'] = ((int) ($result['summary']['malware_hits'] ?? 0) > 0) ? 'hit' : 'no_hit';
            $updates[] = [
                'id' => $id,
                'domain' => (string) ($domainMap[$id] ?? ''),
                'last_tested_at' => $now,
                'last_test_result' => json_encode($result, JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($updates, 1000) as $chunk) {
            WordPressMaliciousDomain::query()->upsert(
                $chunk,
                ['id'],
                ['last_tested_at', 'last_test_result', 'updated_at']
            );
        }

        return [
            'tested' => count($domainMap),
            'matched' => count($matched),
            'total_malware_hits' => $totalMalwareHits,
        ];
    }

    private function sampleContent(WordPressSignatureSample $sample): string
    {
        if (is_string($sample->content) && $sample->content !== '') {
            return $sample->content;
        }

        return '';
    }

    private function contentContainsDomain(string $content, string $domain): bool
    {
        return preg_match(
            '/(?:https?:\/\/)?(?:www\.)?' . preg_quote($domain, '/') . '(?::\d+)?(?:[\/"\'<\s]|$)/i',
            $content
        ) === 1;
    }

    /**
     * @param  array<int|string, string>  $domainMap
     * @return array<int, array<string, mixed>>
     */
    private function testDomainSet(array $domainMap, bool $persistNoHits): array
    {
        $sampleCount = (int) WordPressSignatureSample::query()->count();
        $now = Carbon::now();
        $results = [];
        $domainLookup = [];

        foreach ($domainMap as $id => $domain) {
            $id = (int) $id;
            $domain = strtolower(trim($domain));

            if ($id < 1 || $domain === '') {
                continue;
            }

            $results[$id] = [
                'summary' => [
                    'sample_count' => $sampleCount,
                    'malware_hits' => 0,
                    'clean_hits' => 0,
                    'false_positive_hits' => 0,
                ],
                'outcome' => 'no_hit',
                'matched_samples' => [],
                'tested_at' => $now->toIso8601String(),
            ];
            $domainLookup[$domain] = $id;
        }

        foreach (WordPressSignatureSample::query()->orderBy('id')->cursor() as $sample) {
            $content = $this->sampleContent($sample);

            if ($content === '') {
                continue;
            }

            $domains = $this->extractDomains($content);

            if ($domains === []) {
                continue;
            }

            foreach ($domains as $domain) {
                $id = $domainLookup[$domain] ?? null;

                if ($id === null) {
                    continue;
                }

                if ($sample->sample_type === 'malware') {
                    $results[$id]['summary']['malware_hits']++;
                } elseif ($sample->sample_type === 'clean') {
                    $results[$id]['summary']['clean_hits']++;
                } else {
                    $results[$id]['summary']['false_positive_hits']++;
                }

                if (count($results[$id]['matched_samples']) < 25) {
                    $results[$id]['matched_samples'][] = [
                        'sample' => $sample->name,
                        'type' => $sample->sample_type,
                        'family' => $sample->family,
                        'original_filename' => $sample->original_filename,
                    ];
                }
            }
        }

        $updates = [];

        foreach ($results as $id => &$result) {
            $result['outcome'] = ((int) ($result['summary']['malware_hits'] ?? 0) > 0) ? 'hit' : 'no_hit';

            if (! $persistNoHits && $result['outcome'] === 'no_hit') {
                continue;
            }

            $updates[] = [
                'id' => $id,
                'domain' => (string) ($domainMap[$id] ?? ''),
                'last_tested_at' => $now,
                'last_test_result' => json_encode($result, JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ];
        }
        unset($result);

        foreach (array_chunk($updates, 1000) as $chunk) {
            WordPressMaliciousDomain::query()->upsert(
                $chunk,
                ['id'],
                ['last_tested_at', 'last_test_result', 'updated_at']
            );
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    private function extractDomains(string $content): array
    {
        if (! str_contains($content, '.') && ! str_contains($content, 'http')) {
            return [];
        }

        preg_match_all(
            '/https?:\/\/([^\/\s"\'<]+)|\b((?:[a-z0-9-]+\.)+[a-z]{2,24})\b/i',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        if ($matches === []) {
            return [];
        }

        $domains = [];

        foreach ($matches as $match) {
            $domain = strtolower((string) ($match[1] !== '' ? $match[1] : $match[2]));
            $domain = preg_replace('/:\d+$/', '', $domain);
            $domain = preg_replace('/^www\./', '', $domain);
            $domain = rtrim($domain, '.');

            if (! is_string($domain) || $domain === '') {
                continue;
            }

            $domains[$domain] = $domain;
        }

        return array_values($domains);
    }
}
