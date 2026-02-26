<?php

namespace App\Services\Firewall;

use App\Models\Site;
use App\Services\Aws\AwsFirewallInsightsService;
use App\Services\Bunny\BunnyFirewallInsightsService;

class FirewallInsightsPresenter
{
    public function insights(Site $site): array
    {
        if ($site->provider === Site::PROVIDER_BUNNY) {
            return app(BunnyFirewallInsightsService::class)->getSiteInsights($site);
        }

        return app(AwsFirewallInsightsService::class)->getSiteInsights($site);
    }

    public function forget(Site $site): void
    {
        if ($site->provider === Site::PROVIDER_BUNNY) {
            app(BunnyFirewallInsightsService::class)->forgetSiteInsightsCache($site->id);

            return;
        }

        app(AwsFirewallInsightsService::class)->forgetSiteInsightsCache($site->id);
    }

    public function threatLevel(array $insights): string
    {
        $ratio = (float) data_get($insights, 'summary.block_ratio', 0);

        return match (true) {
            $ratio >= 30 => 'Under Attack',
            $ratio >= 12 => 'Degraded',
            default => 'Healthy',
        };
    }

    public function suspiciousRequests(array $insights): int
    {
        $total = (int) data_get($insights, 'summary.total', 0);
        $blocked = (int) data_get($insights, 'summary.blocked', 0);

        return max(0, (int) round(($total - $blocked) * 0.12));
    }

    /**
     * @return array<int, array{country:string,requests:int,blocked_pct:float,suspicious_pct:float,x:float,y:float,size:int,intensity:float}>
     */
    public function mapPoints(array $insights): array
    {
        $countries = (array) ($insights['top_countries'] ?? []);

        if ($countries === []) {
            return [];
        }

        $coords = [
            'US' => ['x' => 18, 'y' => 42, 'lat' => 39.5, 'lng' => -98.35],
            'CA' => ['x' => 17, 'y' => 27, 'lat' => 56.13, 'lng' => -106.34],
            'BR' => ['x' => 29, 'y' => 68, 'lat' => -14.23, 'lng' => -51.92],
            'GB' => ['x' => 43, 'y' => 33, 'lat' => 55.37, 'lng' => -3.43],
            'FR' => ['x' => 45, 'y' => 37, 'lat' => 46.23, 'lng' => 2.21],
            'DE' => ['x' => 47, 'y' => 35, 'lat' => 51.17, 'lng' => 10.45],
            'NL' => ['x' => 45, 'y' => 34, 'lat' => 52.13, 'lng' => 5.29],
            'ES' => ['x' => 43, 'y' => 43, 'lat' => 40.46, 'lng' => -3.75],
            'IN' => ['x' => 64, 'y' => 50, 'lat' => 20.59, 'lng' => 78.96],
            'JP' => ['x' => 82, 'y' => 43, 'lat' => 36.20, 'lng' => 138.25],
            'AU' => ['x' => 82, 'y' => 77, 'lat' => -25.27, 'lng' => 133.77],
            'SG' => ['x' => 71, 'y' => 62, 'lat' => 1.35, 'lng' => 103.82],
            'ZA' => ['x' => 51, 'y' => 81, 'lat' => -30.56, 'lng' => 22.94],
            'AE' => ['x' => 57, 'y' => 50, 'lat' => 23.42, 'lng' => 53.85],
            'SE' => ['x' => 49, 'y' => 24, 'lat' => 60.13, 'lng' => 18.64],
        ];

        $max = max(1, (int) collect($countries)->max('requests'));
        $blockedPct = (float) data_get($insights, 'summary.block_ratio', 0);
        $suspiciousPct = min(80.0, round($blockedPct * 0.8, 2));

        return collect($countries)
            ->map(function (array $row) use ($coords, $max, $blockedPct, $suspiciousPct): ?array {
                $country = strtoupper((string) ($row['country'] ?? ''));
                $requestCount = (int) ($row['requests'] ?? 0);

                if (! isset($coords[$country])) {
                    return null;
                }

                return [
                    'country' => $country,
                    'requests' => $requestCount,
                    'blocked_pct' => $blockedPct,
                    'suspicious_pct' => $suspiciousPct,
                    'x' => (float) $coords[$country]['x'],
                    'y' => (float) $coords[$country]['y'],
                    'lat' => (float) $coords[$country]['lat'],
                    'lng' => (float) $coords[$country]['lng'],
                    'size' => 6 + (int) round(($requestCount / $max) * 12),
                    'intensity' => round(($requestCount / $max) * 100, 2),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
