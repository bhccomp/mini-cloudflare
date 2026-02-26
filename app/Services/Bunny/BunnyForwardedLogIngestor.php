<?php

namespace App\Services\Bunny;

use App\Models\EdgeRequestLog;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class BunnyForwardedLogIngestor
{
    /**
     * @var array<string, int|null>
     */
    protected array $siteCache = [];

    public function ingest(string $payload): int
    {
        $records = $this->extractRecords($payload);
        $inserted = 0;

        foreach ($records as $record) {
            $host = $this->extractHost($record);
            $siteId = $this->resolveSiteId($host);

            if (! $siteId) {
                continue;
            }

            $ip = (string) $this->value($record, ['ip', 'remote_ip', 'RemoteIp'], '-');
            $country = strtoupper((string) $this->value($record, ['country', 'country_code', 'Country'], '??'));
            $method = strtoupper((string) $this->value($record, ['method', 'Method'], 'GET'));
            $path = (string) $this->value($record, ['path', 'uri', 'PathAndQuery'], '/');
            $statusCode = (int) $this->value($record, ['status_code', 'status', 'Status'], 200);
            $action = strtoupper((string) Arr::get($record, 'action', $statusCode >= 400 ? 'BLOCK' : 'ALLOW'));
            $rule = (string) $this->value($record, ['rule', 'Rule'], 'edge');
            $userAgent = (string) $this->value($record, ['user_agent', 'ua', 'UserAgent'], '');
            $eventAt = $this->parseTimestamp((string) $this->value($record, ['timestamp', 'time', 'Timestamp'], now()->toIso8601String()));

            EdgeRequestLog::query()->create([
                'site_id' => $siteId,
                'event_at' => $eventAt,
                'ip' => $ip,
                'country' => $country,
                'method' => $method,
                'host' => $host,
                'path' => parse_url($path, PHP_URL_PATH) ?: $path,
                'status_code' => $statusCode,
                'action' => $action,
                'rule' => $rule,
                'user_agent' => $userAgent,
                'meta' => [
                    'source' => 'bunny_forwarded',
                    'raw' => $record,
                ],
            ]);

            $inserted++;
        }

        return $inserted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractRecords(string $payload): array
    {
        $payload = trim($payload);

        if ($payload === '') {
            return [];
        }

        $json = $this->decodeEmbeddedJson($payload);

        if (is_array($json)) {
            if (array_is_list($json)) {
                return array_values(array_filter($json, 'is_array'));
            }

            foreach (['records', 'items', 'logs', 'entries', 'data'] as $key) {
                $rows = $json[$key] ?? null;

                if (is_array($rows) && array_is_list($rows)) {
                    return array_values(array_filter($rows, 'is_array'));
                }
            }

            return [$json];
        }

        $plain = $this->parsePlainLine($payload);

        return $plain ? [$plain] : [];
    }

    protected function decodeEmbeddedJson(string $payload): mixed
    {
        $start = strpos($payload, '{');
        $end = strrpos($payload, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($payload, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parsePlainLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $pattern = '/^(?<ip>\\S+)\\s+\\S+\\s+\\S+\\s+\\[(?<time>[^\\]]+)\\]\\s+"(?<method>[A-Z]+)\\s+(?<path>[^\\s"]+)[^"]*"\\s+(?<status>\\d{3})\\s+\\S+\\s+"[^"]*"\\s+"(?<ua>[^"]*)"(?:\\s+"(?<host>[^"]*)")?/';

        if (! preg_match($pattern, $line, $matches)) {
            return null;
        }

        return [
            'ip' => $matches['ip'] ?? '-',
            'timestamp' => $matches['time'] ?? now()->toIso8601String(),
            'method' => $matches['method'] ?? 'GET',
            'path' => $matches['path'] ?? '/',
            'status_code' => (int) ($matches['status'] ?? 200),
            'user_agent' => $matches['ua'] ?? '',
            'host' => $matches['host'] ?? '',
        ];
    }

    protected function extractHost(array $record): string
    {
        $host = (string) $this->value($record, ['host', 'hostname', 'domain', 'Host'], '');

        if ($host !== '') {
            return strtolower(trim($host));
        }

        $path = (string) Arr::get($record, 'path', Arr::get($record, 'uri', ''));

        $parsed = parse_url($path, PHP_URL_HOST);

        return strtolower((string) $parsed);
    }

    protected function resolveSiteId(string $host): ?int
    {
        if ($host === '') {
            return null;
        }

        if (array_key_exists($host, $this->siteCache)) {
            return $this->siteCache[$host];
        }

        $site = Site::query()
            ->where(function ($query) use ($host): void {
                $query->where('apex_domain', $host)
                    ->orWhere('www_domain', $host);
            })
            ->first(['id']);

        $this->siteCache[$host] = $site?->id;

        return $site?->id;
    }

    protected function parseTimestamp(string $value): Carbon
    {
        if (is_numeric($value)) {
            $timestamp = (int) $value;

            // Bunny forwarded logs provide milliseconds since epoch.
            if ($timestamp > 9999999999) {
                return Carbon::createFromTimestampMsUTC($timestamp)->utc();
            }

            return Carbon::createFromTimestampUTC($timestamp)->utc();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $keys
     */
    protected function value(array $record, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        return $default;
    }
}
