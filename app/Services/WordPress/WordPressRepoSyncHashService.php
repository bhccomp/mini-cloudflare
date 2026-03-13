<?php

namespace App\Services\WordPress;

use App\Models\WordPressRepoSyncHash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WordPressRepoSyncHashService
{
    /**
     * @var array<string, string>
     */
    private const FEED_URLS = [
        'md5' => 'https://raw.githubusercontent.com/romainmarcoux/malicious-hash/main/full-hash-md5-aa.txt',
        'sha1' => 'https://raw.githubusercontent.com/romainmarcoux/malicious-hash/main/full-hash-sha1-aa.txt',
        'sha256' => 'https://raw.githubusercontent.com/romainmarcoux/malicious-hash/main/full-hash-sha256-aa.txt',
    ];

    /**
     * @return array{imported:int,updated:int,skipped:int}
     */
    public function syncFromGitHub(): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach (self::FEED_URLS as $algorithm => $url) {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                continue;
            }

            [$chunkImported, $chunkUpdated, $chunkSkipped] = $this->importFeedContents($algorithm, (string) $response->body());
            $imported += $chunkImported;
            $updated += $chunkUpdated;
            $skipped += $chunkSkipped;
        }

        return compact('imported', 'updated', 'skipped');
    }

    /**
     * @return array<int, array{algorithm:string,hash_value:string,label:string}>
     */
    public function activeHashesForManifest(): array
    {
        return WordPressRepoSyncHash::query()
            ->where('status', 'active')
            ->orderBy('algorithm')
            ->orderBy('hash_value')
            ->get(['algorithm', 'hash_value'])
            ->map(fn (WordPressRepoSyncHash $hash): array => [
                'algorithm' => $hash->algorithm,
                'hash_value' => $hash->hash_value,
                'label' => 'known malware hash',
            ])
            ->all();
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function importFeedContents(string $algorithm, string $contents): array
    {
        $algorithm = strtolower(trim($algorithm));

        if (! in_array($algorithm, ['md5', 'sha1', 'sha256'], true)) {
            return [0, 0, 0];
        }

        $expectedLength = match ($algorithm) {
            'md5' => 32,
            'sha1' => 40,
            default => 64,
        };

        $buffer = [];
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $timestamp = now();

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $hash = strtolower(trim((string) $line));

            if ($hash === '' || strlen($hash) !== $expectedLength || ! ctype_xdigit($hash)) {
                $skipped++;
                continue;
            }

            $buffer[$hash] = [
                'algorithm' => $algorithm,
                'hash_value' => $hash,
                'status' => 'active',
                'source' => 'romainmarcoux_malicious_hash',
                'last_synced_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if (count($buffer) < 2000) {
                continue;
            }

            [$chunkImported, $chunkUpdated] = $this->upsertChunk(array_values($buffer));
            $imported += $chunkImported;
            $updated += $chunkUpdated;
            $buffer = [];
        }

        if ($buffer !== []) {
            [$chunkImported, $chunkUpdated] = $this->upsertChunk(array_values($buffer));
            $imported += $chunkImported;
            $updated += $chunkUpdated;
        }

        return [$imported, $updated, $skipped];
    }

    /**
     * @param  array<int, array{algorithm:string,hash_value:string,status:string,source:string,last_synced_at:mixed,created_at:mixed,updated_at:mixed}>  $rows
     * @return array{0:int,1:int}
     */
    private function upsertChunk(array $rows): array
    {
        if ($rows === []) {
            return [0, 0];
        }

        $existing = WordPressRepoSyncHash::query()
            ->where(function ($query) use ($rows): void {
                foreach ($rows as $row) {
                    $query->orWhere(function ($nested) use ($row): void {
                        $nested
                            ->where('algorithm', $row['algorithm'])
                            ->where('hash_value', $row['hash_value']);
                    });
                }
            })
            ->get(['algorithm', 'hash_value'])
            ->map(fn (WordPressRepoSyncHash $hash): string => $hash->algorithm . ':' . $hash->hash_value)
            ->all();

        $existingLookup = array_fill_keys($existing, true);
        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $key = $row['algorithm'] . ':' . $row['hash_value'];

            if (isset($existingLookup[$key])) {
                $updated++;
            } else {
                $imported++;
            }
        }

        WordPressRepoSyncHash::query()->upsert(
            $rows,
            ['algorithm', 'hash_value'],
            ['status', 'source', 'last_synced_at', 'updated_at']
        );

        return [$imported, $updated];
    }
}
