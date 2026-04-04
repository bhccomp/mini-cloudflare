<?php

namespace App\Services\Dns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class CloudflareDnsService
{
    public function isConfigured(): bool
    {
        return filled(config('services.cloudflare.zone_id'))
            && filled(config('services.cloudflare.api_token'))
            && filled(config('services.cloudflare.edge_alias_base_domain'));
    }

    public function edgeAliasBaseDomain(): string
    {
        return strtolower(trim((string) config('services.cloudflare.edge_alias_base_domain')));
    }

    public function edgeAliasHostname(string $label): string
    {
        return strtolower(trim($label).'.'.$this->edgeAliasBaseDomain());
    }

    /**
     * @return array{id:string,hostname:string,target:string}
     */
    public function upsertEdgeAlias(string $hostname, string $target): array
    {
        $record = $this->findRecordByName($hostname);

        $payload = [
            'type' => 'CNAME',
            'name' => $hostname,
            'content' => $target,
            'ttl' => 1,
            'proxied' => false,
            'comment' => 'FirePhage managed Bunny edge alias',
        ];

        $result = $record
            ? $this->request()->put('/'.rawurlencode((string) Arr::get($record, 'id')), $payload)->throw()->json('result')
            : $this->request()->post('', $payload)->throw()->json('result');

        return [
            'id' => (string) Arr::get($result, 'id', Arr::get($record, 'id', '')),
            'hostname' => strtolower((string) Arr::get($result, 'name', $hostname)),
            'target' => strtolower((string) Arr::get($result, 'content', $target)),
        ];
    }

    public function deleteEdgeAlias(?string $recordId, ?string $hostname = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        if ($recordId) {
            $response = $this->request()->delete('/'.rawurlencode($recordId));

            if ($response->successful() || in_array($response->status(), [404, 410], true)) {
                return;
            }

            $response->throw();
        }

        if (! $hostname) {
            return;
        }

        $record = $this->findRecordByName($hostname);
        if (! $record) {
            return;
        }

        $response = $this->request()->delete('/'.rawurlencode((string) Arr::get($record, 'id')));

        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }
    }

    protected function findRecordByName(string $hostname): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = $this->request()->get('', [
            'type' => 'CNAME',
            'name' => strtolower($hostname),
            'per_page' => 1,
        ])->throw()->json('result');

        return is_array($response) && isset($response[0]) && is_array($response[0]) ? $response[0] : null;
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(sprintf(
            'https://api.cloudflare.com/client/v4/zones/%s/dns_records',
            rawurlencode((string) config('services.cloudflare.zone_id'))
        ))->acceptJson()->withToken((string) config('services.cloudflare.api_token'));
    }
}
