<?php

namespace App\Services\Sites;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class SiteRoutingStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function statusForSite(Site $site, bool $fresh = false): array
    {
        if ($site->isDemoSeeded()) {
            $demoStatus = data_get($site->required_dns_records, 'demo_routing_status');

            if (is_array($demoStatus)) {
                $demoStatus['checked_at'] = now();

                return $demoStatus;
            }
        }

        $key = $this->cacheKey($site);

        if ($fresh) {
            Cache::forget($key);
        }

        /** @var array<string, mixed> */
        return Cache::remember($key, now()->addSeconds(60), function () use ($site): array {
            $expectedTarget = $this->expectedTarget($site);
            if ($expectedTarget === '') {
                return [
                    'status' => 'unavailable',
                    'label' => 'Unavailable',
                    'color' => 'gray',
                    'message' => 'Edge target is not configured yet.',
                    'expected_target' => null,
                    'checked_at' => now(),
                    'domains' => [],
                ];
            }

            $domains = [$site->apex_domain];
            if ($site->www_enabled && $site->www_domain) {
                $domains[] = $site->www_domain;
            }

            $rows = [];
            $validCount = 0;

            foreach ($domains as $domain) {
                $resolved = $this->resolveDns($domain);
                $pointsToEdge = $this->domainPointsToTarget($site, $domain, $resolved, $expectedTarget);

                if ($pointsToEdge) {
                    $validCount++;
                }

                $rows[] = [
                    'domain' => $domain,
                    'points_to_edge' => $pointsToEdge,
                    'resolved' => $resolved,
                ];
            }

            $status = match (true) {
                $validCount === count($rows) && $validCount > 0 => 'protected',
                $validCount > 0 => 'partial',
                default => 'drift',
            };

            [$label, $color] = match ($status) {
                'protected' => ['Protected', 'success'],
                'partial' => ['Partially Routed', 'warning'],
                default => ['Routing Drift Detected', 'danger'],
            };

            $message = $this->statusMessage($status, $rows, $expectedTarget);

            return [
                'status' => $status,
                'label' => $label,
                'color' => $color,
                'message' => $message,
                'expected_target' => $expectedTarget,
                'checked_at' => now(),
                'domains' => $rows,
            ];
        });
    }

    public function forget(Site $site): void
    {
        Cache::forget($this->cacheKey($site));
    }

    protected function cacheKey(Site $site): string
    {
        return 'site-routing-status:'.$site->id.':'.md5((string) $this->expectedTarget($site));
    }

    protected function expectedTarget(Site $site): string
    {
        $target = trim((string) data_get($site->required_dns_records, 'traffic.0.value', ''));

        if ($target !== '') {
            return rtrim(strtolower($target), '.');
        }

        return rtrim(strtolower((string) ($site->cloudfront_domain_name ?? '')), '.');
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
        $process->setTimeout(5);
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

        $process = new Process(['dig', '+short', $type, $name]);
        $process->setTimeout(5);
        $process->run();

        $records = collect(explode("\n", trim($process->getOutput())))->filter()->values()->all();
        if ($records !== []) {
            return $records;
        }

        $dnsType = strtoupper($type) === 'AAAA' ? DNS_AAAA : DNS_A;
        $fallback = dns_get_record($name, $dnsType);

        if (! is_array($fallback)) {
            return [];
        }

        $key = strtoupper($type) === 'AAAA' ? 'ipv6' : 'ip';

        return collect($fallback)
            ->map(fn (array $record) => (string) ($record[$key] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array{cname: array<int, string>, a: array<int, string>, aaaa: array<int, string>}  $resolved
     */
    protected function domainPointsToTarget(Site $site, string $domain, array $resolved, string $target): bool
    {
        $target = rtrim(strtolower($target), '.');

        $cname = collect($resolved['cname'] ?? [])
            ->map(fn (string $value) => rtrim(strtolower($value), '.'))
            ->contains($target);

        if ($cname) {
            return true;
        }

        if ($site->provider === Site::PROVIDER_BUNNY) {
            $hostnameStatus = collect((array) data_get($site->provider_meta, 'hostnames', []))
                ->first(fn (array $row): bool => rtrim(strtolower((string) ($row['hostname'] ?? '')), '.') === rtrim(strtolower($domain), '.'));

            if (($hostnameStatus['ok'] ?? false) === true) {
                return true;
            }
        }

        $targetIps = array_values(array_unique(array_merge(
            gethostbynamel($target) ?: [],
            $this->lookupByType($target, 'AAAA')
        )));

        $domainIps = array_values(array_unique(array_merge(
            $resolved['a'] ?? [],
            $resolved['aaaa'] ?? []
        )));

        if ($domainIps === [] || $targetIps === []) {
            return false;
        }

        return count(array_intersect($domainIps, $targetIps)) > 0;
    }

    /**
     * @param  array<int, array{domain:string, points_to_edge:bool, resolved:array{cname:array<int,string>,a:array<int,string>,aaaa:array<int,string>}}>  $rows
     */
    protected function statusMessage(string $status, array $rows, string $expectedTarget): string
    {
        if ($status === 'protected') {
            return 'Traffic is routed through the FirePhage edge target.';
        }

        $notRouted = collect($rows)
            ->filter(fn (array $row): bool => ! $row['points_to_edge'])
            ->pluck('domain')
            ->values()
            ->all();

        $routed = collect($rows)
            ->filter(fn (array $row): bool => $row['points_to_edge'])
            ->pluck('domain')
            ->values()
            ->all();

        $missingList = $this->formatDomainList($notRouted);
        $routedList = $this->formatDomainList($routed);

        if ($status === 'partial') {
            return $missingList === ''
                ? 'Some DNS records still need to be updated to the FirePhage edge target.'
                : "DNS is only partially updated. {$missingList} still need to point to {$expectedTarget}, while {$routedList} are already routed correctly.";
        }

        return $missingList === ''
            ? "DNS is not pointed to the expected FirePhage edge target yet. Update your traffic records to {$expectedTarget}."
            : "{$missingList} are still pointed somewhere else. Update them to {$expectedTarget} to finish routing traffic through FirePhage.";
    }

    /**
     * @param  array<int, string>  $domains
     */
    protected function formatDomainList(array $domains): string
    {
        $domains = array_values(array_filter($domains));

        return match (count($domains)) {
            0 => '',
            1 => $domains[0],
            2 => $domains[0].' and '.$domains[1],
            default => implode(', ', array_slice($domains, 0, -1)).', and '.end($domains),
        };
    }
}
