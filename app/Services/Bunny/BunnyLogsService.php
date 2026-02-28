<?php

namespace App\Services\Bunny;

use App\Models\EdgeRequestLog;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BunnyLogsService
{
    public function __construct(protected BunnyApiService $api) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentLogs(Site $site, int $limit = 200): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return [];
        }

        $responses = [
            $this->api->client()->get('/logging', [
                'pullZone' => $zoneId,
                'limit' => $limit,
                'sort' => 'desc',
            ]),
            $this->api->client()->get("/pullzone/{$zoneId}/logs", [
                'limit' => $limit,
            ]),
        ];

        foreach ($responses as $response) {
            if (! $response->successful()) {
                continue;
            }

            $payload = $response->json();
            $rows = $this->extractRows($payload);

            if ($rows === []) {
                continue;
            }

            return collect($rows)
                ->map(fn (array $row): array => $this->normalizeRow($row))
                ->filter(fn (array $row): bool => $row['ip'] !== '-' || $row['uri'] !== '/')
                ->values()
                ->take($limit)
                ->all();
        }

        $archiveRows = $this->downloadLogArchiveRows($site, $limit);
        if ($archiveRows !== []) {
            return collect($archiveRows)
                ->map(fn (array $row): array => $this->normalizeRow($row))
                ->filter(fn (array $row): bool => $row['ip'] !== '-' || $row['uri'] !== '/')
                ->values()
                ->take($limit)
                ->all();
        }

        return [];
    }

    public function syncToLocalStore(Site $site, int $limit = 500): int
    {
        $rows = $this->recentLogs($site, $limit);

        if ($rows === []) {
            return 0;
        }

        $payload = collect($rows)
            ->map(function (array $row) use ($site): array {
                $eventAt = $row['timestamp'] ?? now();
                if (! $eventAt instanceof Carbon) {
                    $eventAt = Carbon::parse((string) $eventAt);
                }

                return [
                    'site_id' => $site->id,
                    'event_at' => $eventAt->toDateTimeString(),
                    'ip' => (string) ($row['ip'] ?? '-'),
                    'country' => strtoupper((string) ($row['country'] ?? '??')),
                    'method' => strtoupper((string) ($row['method'] ?? 'GET')),
                    'host' => (string) ($row['host'] ?? parse_url((string) ($row['uri'] ?? ''), PHP_URL_HOST) ?? ''),
                    'path' => (string) (parse_url((string) ($row['uri'] ?? '/'), PHP_URL_PATH) ?: ($row['uri'] ?? '/')),
                    'status_code' => (int) ($row['status_code'] ?? 0),
                    'action' => strtoupper((string) ($row['action'] ?? 'ALLOW')),
                    'rule' => (string) ($row['rule'] ?? 'edge'),
                    'user_agent' => (string) ($row['user_agent'] ?? data_get($row, 'meta.user_agent', '')),
                    'meta' => [
                        'bytes' => (int) ($row['bytes'] ?? 0),
                        'source' => 'bunny',
                    ],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        EdgeRequestLog::query()->insert($payload);

        return count($payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractRows(mixed $payload): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        if (! is_array($payload)) {
            return [];
        }

        foreach (['records', 'items', 'logs', 'Rows', 'rows', 'data'] as $key) {
            $rows = $payload[$key] ?? null;

            if (is_array($rows) && array_is_list($rows)) {
                return array_values(array_filter($rows, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeRow(array $row): array
    {
        $rawTime = (string) (Arr::get($row, 'timestamp')
            ?? Arr::get($row, 'Timestamp')
            ?? Arr::get($row, 'datetime')
            ?? Arr::get($row, 'dateTime')
            ?? Arr::get($row, 'time')
            ?? now()->toIso8601String());

        $status = (int) (Arr::get($row, 'status')
            ?? Arr::get($row, 'StatusCode')
            ?? Arr::get($row, 'responseStatus')
            ?? 200);

        $action = (string) (Arr::get($row, 'action')
            ?? Arr::get($row, 'Action')
            ?? ($status >= 400 ? 'BLOCK' : 'ALLOW'));

        return [
            'timestamp' => $this->parseTimestampSafely($rawTime),
            'action' => strtoupper($action),
            'ip' => (string) (Arr::get($row, 'ip') ?? Arr::get($row, 'IP') ?? Arr::get($row, 'remoteIp') ?? '-'),
            'country' => strtoupper((string) (Arr::get($row, 'country') ?? Arr::get($row, 'countryCode') ?? Arr::get($row, 'CountryCode') ?? '??')),
            'method' => strtoupper((string) (Arr::get($row, 'method') ?? Arr::get($row, 'Method') ?? 'GET')),
            'uri' => (string) (Arr::get($row, 'url') ?? Arr::get($row, 'Url') ?? Arr::get($row, 'path') ?? '/'),
            'rule' => (string) (Arr::get($row, 'rule') ?? Arr::get($row, 'Rule') ?? Arr::get($row, 'cacheStatus') ?? 'edge'),
            'status_code' => $status,
            'bytes' => (int) (Arr::get($row, 'bytes') ?? Arr::get($row, 'Bytes') ?? 0),
        ];
    }

    private function parseTimestampSafely(string $raw): Carbon
    {
        $candidate = trim($raw);

        if ($candidate === '') {
            return now();
        }

        if (preg_match('/^\d{13}$/', $candidate) === 1) {
            return Carbon::createFromTimestampMs((int) $candidate);
        }

        if (preg_match('/^\d{10}$/', $candidate) === 1) {
            return Carbon::createFromTimestamp((int) $candidate);
        }

        if (str_contains($candidate, '|')) {
            foreach (explode('|', $candidate) as $segment) {
                $segment = trim($segment);

                if (preg_match('/^\d{13}$/', $segment) === 1) {
                    return Carbon::createFromTimestampMs((int) $segment);
                }

                if (preg_match('/^\d{10}$/', $segment) === 1) {
                    return Carbon::createFromTimestamp((int) $segment);
                }
            }
        }

        try {
            return Carbon::parse($candidate);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function downloadLogArchiveRows(Site $site, int $limit): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return [];
        }

        $accessKey = $this->api->apiKey();
        if ($accessKey === '') {
            return [];
        }

        $base = rtrim((string) config('edge.bunny.logging_base_url', 'https://logging.bunnycdn.com'), '/');
        $rows = [];

        foreach (range(0, 2) as $daysAgo) {
            $datePath = now()->subDays($daysAgo)->format('m-d-y');
            $url = "{$base}/{$datePath}/{$zoneId}.log";

            try {
                $response = Http::timeout(20)
                    ->withHeaders([
                        'AccessKey' => $accessKey,
                        'Accept-Encoding' => 'gzip',
                    ])
                    ->get($url);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $lines = preg_split('/\r\n|\r|\n/', (string) $response->body()) ?: [];

            foreach ($lines as $line) {
                $parsed = $this->parseArchiveLine($line);

                if ($parsed !== null) {
                    $rows[] = $parsed;
                }
            }

            if (count($rows) >= $limit) {
                break;
            }
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseArchiveLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if (Str::startsWith($line, '{')) {
            $json = json_decode($line, true);

            return is_array($json) ? $json : null;
        }

        $parts = str_contains($line, "\t")
            ? explode("\t", $line)
            : preg_split('/\s+/', $line, 14);

        if (! is_array($parts) || count($parts) < 4) {
            return null;
        }

        $ip = collect($parts)->first(fn ($part) => filter_var($part, FILTER_VALIDATE_IP));
        $method = collect($parts)->first(fn ($part) => in_array(strtoupper((string) $part), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true));
        $statusRaw = collect($parts)->first(fn ($part) => preg_match('/^[1-5][0-9]{2}$/', (string) $part) === 1);
        $uri = collect($parts)->first(fn ($part) => str_starts_with((string) $part, '/')) ?: '/';
        $country = collect($parts)->first(fn ($part) => preg_match('/^[A-Za-z]{2}$/', (string) $part) === 1) ?: '??';

        $date = (string) ($parts[0] ?? '');
        $time = (string) ($parts[1] ?? '');
        $timestamp = trim($date.' '.$time);

        return [
            'timestamp' => $timestamp,
            'action' => ((int) $statusRaw) >= 400 ? 'BLOCK' : 'ALLOW',
            'ip' => (string) ($ip ?: '-'),
            'country' => strtoupper((string) $country),
            'method' => strtoupper((string) ($method ?: 'GET')),
            'uri' => (string) $uri,
            'rule' => 'edge',
            'status_code' => (int) ($statusRaw ?: 200),
            'bytes' => 0,
        ];
    }
}
