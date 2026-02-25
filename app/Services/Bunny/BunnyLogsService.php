<?php

namespace App\Services\Bunny;

use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Arr;

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

        return [];
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
            'timestamp' => Carbon::parse($rawTime),
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
}
