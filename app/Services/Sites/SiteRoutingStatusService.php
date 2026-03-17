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
                $pointsToEdge = $this->domainPointsToTarget($resolved, $expectedTarget);

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

            [$label, $color, $message] = match ($status) {
                'protected' => ['Protected', 'success', 'Traffic is routed through the edge network.'],
                'partial' => ['Partially Routed', 'warning', 'Some hostnames are still routed through the edge network, but not all of them.'],
                default => ['Routing Drift Detected', 'danger', 'This site is no longer routed through the expected edge target.'],
            };

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
    protected function domainPointsToTarget(array $resolved, string $target): bool
    {
        $target = rtrim(strtolower($target), '.');

        $cname = collect($resolved['cname'] ?? [])
            ->map(fn (string $value) => rtrim(strtolower($value), '.'))
            ->contains($target);

        if ($cname) {
            return true;
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
}
