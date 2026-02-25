<?php

namespace App\Services\Edge\Providers;

use App\Models\Site;
use App\Models\SystemSetting;
use App\Services\Edge\EdgeProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BunnyCdnProvider implements EdgeProviderInterface
{
    public function key(): string
    {
        return Site::PROVIDER_BUNNY;
    }

    public function requiresCertificateValidation(): bool
    {
        return false;
    }

    public function requestCertificate(Site $site): array
    {
        return [
            'changed' => false,
            'required_dns_records' => $site->required_dns_records ?? [],
            'message' => 'Bunny provider does not require ACM DNS validation before deployment.',
        ];
    }

    public function checkCertificateValidation(Site $site): array
    {
        return [
            'validated' => true,
            'required_dns_records' => $site->required_dns_records ?? [],
            'message' => 'Bunny provider is ready for deployment.',
        ];
    }

    public function provision(Site $site): array
    {
        $existingId = (int) ($site->provider_resource_id ?: 0);
        $zoneId = $existingId;
        $zoneName = (string) data_get($site->provider_meta, 'zone_name', '');

        if ($zoneId <= 0) {
            $zoneName = $this->zoneNameFor($site);

            $created = $this->client()->post('/pullzone', [
                'Name' => $zoneName,
                'OriginUrl' => $site->origin_url,
                'Type' => 0,
            ])->throw()->json();

            $zoneId = (int) (Arr::get($created, 'Id') ?? Arr::get($created, 'id') ?? 0);
            $zoneName = (string) (Arr::get($created, 'Name') ?? $zoneName);
        }

        $edgeDomain = $this->zoneEdgeDomain($zoneName);
        $hostnames = [$site->apex_domain];

        if ($site->www_enabled && $site->www_domain) {
            $hostnames[] = $site->www_domain;
        }

        $hostnameResults = [];
        foreach ($hostnames as $hostname) {
            $response = $this->client()->post("/pullzone/{$zoneId}/addHostName", [
                'Hostname' => $hostname,
            ]);

            $hostnameResults[] = [
                'hostname' => $hostname,
                'ok' => $response->successful(),
                'status' => $response->status(),
            ];
        }

        $requiredDnsRecords = [
            'traffic' => $this->trafficRecords($site, $edgeDomain),
        ];

        return [
            'ok' => true,
            'status' => Site::STATUS_DEPLOYING,
            'provider' => $this->key(),
            'provider_resource_id' => (string) $zoneId,
            'provider_meta' => [
                'zone_id' => $zoneId,
                'zone_name' => $zoneName,
                'edge_domain' => $edgeDomain,
                'hostnames' => $hostnameResults,
            ],
            'distribution_id' => (string) $zoneId,
            'distribution_domain_name' => $edgeDomain,
            'required_dns_records' => $requiredDnsRecords,
            'dns_records' => $requiredDnsRecords['traffic'],
            'notes' => ['Bunny Pull Zone provisioned. Point DNS traffic records to the Bunny edge domain.'],
        ];
    }

    public function checkDns(Site $site): array
    {
        $target = rtrim(strtolower((string) $site->cloudfront_domain_name), '.');
        if ($target === '') {
            return [
                'validated' => false,
                'required_dns_records' => $site->required_dns_records,
                'message' => 'Edge target is missing.',
            ];
        }

        $domains = [$site->apex_domain];
        if ($site->www_enabled && $site->www_domain) {
            $domains[] = $site->www_domain;
        }

        $allValid = true;
        $dns = $site->required_dns_records ?? [];
        $traffic = Arr::get($dns, 'traffic', []);
        $resolved = [];

        foreach ($domains as $domain) {
            $lookup = $this->resolveDns($domain);
            $resolved[$domain] = $lookup;
            $valid = $this->domainPointsToTarget($lookup, $target);
            $allValid = $allValid && $valid;

            foreach ($traffic as &$record) {
                if (($record['name'] ?? null) === $domain) {
                    $record['status'] = $valid ? 'verified' : 'pending';
                }
            }
            unset($record);
        }

        $dns['traffic'] = $traffic;

        $message = $allValid
            ? 'Traffic DNS is pointed to Bunny edge target.'
            : 'Point your domain to the Bunny edge target and retry.';

        if (! $allValid) {
            $ssl = $this->checkSsl($site);

            if (in_array(($ssl['status'] ?? ''), ['pending', 'active'], true)) {
                $allValid = true;
                $message = 'Bunny detected your hostname. DNS is accepted and SSL is provisioning.';
            }
        }

        return [
            'validated' => $allValid,
            'ok' => $allValid,
            'resolved_targets' => $resolved,
            'expected_target' => $target,
            'required_dns_records' => $dns,
            'message' => $message,
        ];
    }

    public function checkSsl(Site $site): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return ['status' => 'error', 'message' => 'Bunny pull zone is missing.'];
        }

        $response = $this->client()->get("/pullzone/{$zoneId}");

        if (! $response->successful()) {
            return ['status' => 'pending', 'message' => 'Unable to read Bunny SSL state yet.'];
        }

        $payload = $response->json();
        $hostnames = collect(Arr::get($payload, 'Hostnames', Arr::get($payload, 'hostnames', [])));

        if ($hostnames->isEmpty()) {
            return ['status' => 'pending', 'message' => 'Waiting for Bunny hostname certificate state.'];
        }

        $domains = collect([$site->apex_domain, $site->www_enabled ? $site->www_domain : null])
            ->filter()
            ->values();

        $tracked = $hostnames->filter(function ($row) use ($domains) {
            $value = strtolower((string) Arr::get($row, 'Value', Arr::get($row, 'value', Arr::get($row, 'Hostname', Arr::get($row, 'hostname', '')))));

            return $domains->contains($value);
        });

        if ($tracked->isEmpty()) {
            $tracked = $hostnames;
        }

        $allActive = $tracked->every(function ($row) {
            $status = strtolower((string) Arr::get($row, 'CertificateStatus', Arr::get($row, 'certificateStatus', '')));
            $isValid = Arr::get($row, 'IsCertificateValid', Arr::get($row, 'isCertificateValid'));
            $hasCertificate = Arr::get($row, 'HasCertificate', Arr::get($row, 'hasCertificate'));

            if (is_bool($isValid)) {
                return $isValid;
            }

            if (is_bool($hasCertificate) && $hasCertificate === true) {
                return true;
            }

            return in_array($status, ['active', 'valid', 'issued', 'enabled'], true);
        });

        if ($allActive) {
            return ['status' => 'active', 'message' => 'Bunny SSL certificate is active.'];
        }

        return ['status' => 'pending', 'message' => 'DNS is detected. Waiting for Bunny SSL certificate issuance.'];
    }

    public function purgeCache(Site $site, array $paths = ['/*']): array
    {
        $zoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($zoneId <= 0) {
            return ['changed' => false, 'message' => 'Bunny zone is not provisioned yet.'];
        }

        $paths = array_values($paths);

        if ($paths === [] || $paths === ['/*']) {
            $this->client()->post("/pullzone/{$zoneId}/purgeCache")->throw();

            return [
                'changed' => true,
                'paths' => ['/*'],
                'message' => 'Bunny cache purge requested for all files.',
            ];
        }

        foreach ($paths as $path) {
            $this->client()
                ->withQueryParameters(['url' => $path])
                ->post("/pullzone/{$zoneId}/purgeCache")
                ->throw();
        }

        return [
            'changed' => true,
            'paths' => $paths,
            'message' => 'Bunny cache purge requested for selected paths.',
        ];
    }

    public function setUnderAttackMode(Site $site, bool $enabled): array
    {
        return [
            'changed' => false,
            'enabled' => $enabled,
            'message' => 'Under-attack mode is not supported for Bunny provider yet.',
        ];
    }

    protected function client(): PendingRequest
    {
        $apiKey = $this->bunnyApiKey();

        if ($apiKey === '') {
            throw new \RuntimeException('Bunny API key is not configured in system settings.');
        }

        return Http::baseUrl(rtrim((string) config('edge.bunny.base_url', 'https://api.bunny.net'), '/'))
            ->acceptJson()
            ->withHeaders([
                'AccessKey' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(15);
    }

    protected function bunnyApiKey(): string
    {
        $setting = SystemSetting::query()->where('key', 'bunny')->first();
        $value = $setting?->value;

        if (is_array($value)) {
            return (string) ($value['api_key'] ?? '');
        }

        return '';
    }

    protected function zoneNameFor(Site $site): string
    {
        $base = Str::of($site->apex_domain)
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '-')
            ->trim('-')
            ->limit(35, '')
            ->value();

        return trim("fp-{$site->id}-{$base}", '-');
    }

    protected function zoneEdgeDomain(string $zoneName): string
    {
        return strtolower($zoneName).'.b-cdn.net';
    }

    /**
     * @return array<int, array{purpose:string,type:string,name:string,value:string,ttl:string,status:string,notes:string}>
     */
    protected function trafficRecords(Site $site, string $target): array
    {
        $records = [[
            'purpose' => 'traffic',
            'type' => 'CNAME/ALIAS',
            'name' => $site->apex_domain,
            'value' => $target,
            'ttl' => 'Auto',
            'status' => 'pending',
            'notes' => 'Use ALIAS/ANAME/CNAME flattening for apex if your DNS provider supports it.',
        ]];

        if ($site->www_enabled && $site->www_domain) {
            $records[] = [
                'purpose' => 'traffic',
                'type' => 'CNAME',
                'name' => $site->www_domain,
                'value' => $target,
                'ttl' => 'Auto',
                'status' => 'pending',
                'notes' => 'Point www directly to Bunny edge hostname.',
            ];
        }

        return $records;
    }

    protected function domainPointsToTarget(array $resolved, string $target): bool
    {
        $target = rtrim(strtolower($target), '.');

        $cname = collect((array) ($resolved['cname'] ?? []))
            ->map(fn (string $value) => rtrim(strtolower($value), '.'))
            ->contains($target);

        if ($cname) {
            return true;
        }

        $targetIps = gethostbynamel($target) ?: [];
        $domainIps = array_values(array_unique(array_merge(
            (array) ($resolved['a'] ?? []),
            (array) ($resolved['aaaa'] ?? [])
        )));

        if ($domainIps === [] || $targetIps === []) {
            return false;
        }

        return count(array_intersect($domainIps, $targetIps)) > 0;
    }

    /**
     * @return array{cname: array<int, string>, a: array<int, string>, aaaa: array<int, string>}
     */
    protected function resolveDns(string $domain): array
    {
        return [
            'cname' => $this->lookupCname($domain),
            'a' => $this->lookupByType($domain, 'A'),
            'aaaa' => $this->lookupByType($domain, 'AAAA'),
        ];
    }

    /**
     * @return list<string>
     */
    protected function lookupCname(string $name): array
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return [];
        }

        $process = new Process(['dig', '+short', 'CNAME', $name]);
        $process->setTimeout(10);
        $process->run();

        $records = collect(explode("\n", trim($process->getOutput())))->filter()->values()->all();

        if ($records !== []) {
            return $records;
        }

        $fallback = dns_get_record($name, DNS_CNAME);
        if (! is_array($fallback)) {
            return [];
        }

        return collect($fallback)
            ->map(fn (array $record) => (string) ($record['target'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function lookupByType(string $name, string $type): array
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return [];
        }

        $process = new Process(['dig', '+short', strtoupper($type), $name]);
        $process->setTimeout(10);
        $process->run();

        $records = collect(explode("\n", trim($process->getOutput())))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        if ($records !== []) {
            return $records;
        }

        $dnsType = strtoupper($type) === 'AAAA' ? DNS_AAAA : DNS_A;
        $fallback = dns_get_record($name, $dnsType);
        if (! is_array($fallback)) {
            return [];
        }

        return collect($fallback)
            ->map(function (array $record) use ($type): string {
                return (string) ($type === 'AAAA' ? ($record['ipv6'] ?? '') : ($record['ip'] ?? ''));
            })
            ->filter()
            ->values()
            ->all();
    }
}
