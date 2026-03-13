<?php

namespace App\Services\WordPress;

use App\Models\WordPressMaliciousString;
use App\Models\WordPressSignatureSample;
use Illuminate\Support\Carbon;

class WordPressMaliciousStringService
{
    /**
     * @return array<int, array{needle:string,label:string}>
     */
    public function activeStringsForManifest(): array
    {
        return WordPressMaliciousString::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['name', 'needle'])
            ->map(function (WordPressMaliciousString $string): array {
                return [
                    'needle' => (string) $string->needle,
                    'label' => trim((string) $string->name) !== '' ? (string) $string->name : 'malicious string match',
                ];
            })
            ->filter(fn (array $entry): bool => $entry['needle'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function testString(WordPressMaliciousString $string): array
    {
        $results = $this->testStringSet([$string->id => (string) $string->needle], true);

        return $results[$string->id] ?? [
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
     * @param  iterable<WordPressMaliciousString>  $strings
     * @return array{tested:int,matched:int,total_malware_hits:int}
     */
    public function testSelectedStrings(iterable $strings): array
    {
        $stringMap = [];

        foreach ($strings as $string) {
            if (! $string instanceof WordPressMaliciousString) {
                continue;
            }

            $needle = (string) $string->needle;

            if ($needle === '') {
                continue;
            }

            $stringMap[$string->id] = $needle;
        }

        if ($stringMap === []) {
            return ['tested' => 0, 'matched' => 0, 'total_malware_hits' => 0];
        }

        $results = $this->testStringSet($stringMap, true);

        return [
            'tested' => count($stringMap),
            'matched' => count(array_filter($results, fn (array $result): bool => (int) ($result['summary']['malware_hits'] ?? 0) > 0)),
            'total_malware_hits' => array_sum(array_map(fn (array $result): int => (int) ($result['summary']['malware_hits'] ?? 0), $results)),
        ];
    }

    /**
     * @return array{tested:int,matched:int,total_malware_hits:int}
     */
    public function testAllActiveStrings(): array
    {
        $stringMap = WordPressMaliciousString::query()
            ->where('status', 'active')
            ->pluck('needle', 'id')
            ->map(fn (string $needle): string => (string) $needle)
            ->filter(fn (string $needle): bool => $needle !== '')
            ->all();

        if ($stringMap === []) {
            return ['tested' => 0, 'matched' => 0, 'total_malware_hits' => 0];
        }

        $results = $this->testStringSet($stringMap, true);

        return [
            'tested' => count($stringMap),
            'matched' => count(array_filter($results, fn (array $result): bool => (int) ($result['summary']['malware_hits'] ?? 0) > 0)),
            'total_malware_hits' => array_sum(array_map(fn (array $result): int => (int) ($result['summary']['malware_hits'] ?? 0), $results)),
        ];
    }

    /**
     * @param  array<int, string>  $stringMap
     * @return array<int, array<string, mixed>>
     */
    private function testStringSet(array $stringMap, bool $persist): array
    {
        if ($stringMap === []) {
            return [];
        }

        $sampleCount = (int) WordPressSignatureSample::query()->count();
        $results = [];
        $now = Carbon::now();

        foreach (WordPressSignatureSample::query()->orderBy('id')->cursor() as $sample) {
            $content = $this->sampleContent($sample);

            if ($content === '') {
                continue;
            }

            foreach ($stringMap as $id => $needle) {
                if ($needle === '' || strpos($content, $needle) === false) {
                    continue;
                }

                if (! isset($results[$id])) {
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

        foreach ($results as &$result) {
            $result['outcome'] = ((int) ($result['summary']['malware_hits'] ?? 0) > 0) ? 'hit' : 'no_hit';
        }
        unset($result);

        if ($persist) {
            $updates = [];

            foreach ($results as $id => $result) {
                $updates[] = [
                    'id' => $id,
                    'last_tested_at' => $now,
                    'last_test_result' => json_encode($result, JSON_UNESCAPED_SLASHES),
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($updates, 1000) as $chunk) {
                WordPressMaliciousString::query()->upsert(
                    $chunk,
                    ['id'],
                    ['last_tested_at', 'last_test_result', 'updated_at']
                );
            }
        }

        return $results;
    }

    private function sampleContent(WordPressSignatureSample $sample): string
    {
        return is_string($sample->content) ? $sample->content : '';
    }
}
