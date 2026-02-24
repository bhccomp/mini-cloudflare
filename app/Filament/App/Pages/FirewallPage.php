<?php

namespace App\Filament\App\Pages;

use App\Services\Aws\AwsFirewallInsightsService;

class FirewallPage extends BaseProtectionPage
{
    protected static ?string $slug = 'firewall';

    protected static ?int $navigationSort = -2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Firewall';

    protected static ?string $title = 'Firewall';

    protected string $view = 'filament.app.pages.protection.firewall';

    public function firewallInsights(): array
    {
        if (! $this->site) {
            return [];
        }

        return app(AwsFirewallInsightsService::class)->getSiteInsights($this->site);
    }

    public function refreshFirewallInsights(): void
    {
        if (! $this->site) {
            return;
        }

        app(AwsFirewallInsightsService::class)->forgetSiteInsightsCache($this->site->id);
        $this->notify('Refreshing firewall insights...');
    }

    public function requestMapPoints(): array
    {
        $insights = $this->firewallInsights();
        $countries = (array) ($insights['top_countries'] ?? []);

        if ($countries === []) {
            return [];
        }

        $coords = [
            'US' => ['x' => 20, 'y' => 42],
            'CA' => ['x' => 19, 'y' => 27],
            'BR' => ['x' => 30, 'y' => 68],
            'GB' => ['x' => 45, 'y' => 33],
            'FR' => ['x' => 47, 'y' => 37],
            'DE' => ['x' => 49, 'y' => 35],
            'NL' => ['x' => 47, 'y' => 34],
            'ES' => ['x' => 45, 'y' => 43],
            'IN' => ['x' => 66, 'y' => 50],
            'JP' => ['x' => 84, 'y' => 43],
            'AU' => ['x' => 84, 'y' => 77],
            'SG' => ['x' => 73, 'y' => 62],
            'ZA' => ['x' => 53, 'y' => 81],
            'AE' => ['x' => 59, 'y' => 50],
            'SE' => ['x' => 51, 'y' => 24],
        ];

        $max = max(1, (int) collect($countries)->max('requests'));

        return collect($countries)
            ->map(function (array $row) use ($coords, $max): ?array {
                $country = strtoupper((string) ($row['country'] ?? ''));
                $requestCount = (int) ($row['requests'] ?? 0);

                if (! isset($coords[$country])) {
                    return null;
                }

                $size = 7 + (int) round(($requestCount / $max) * 18);

                return [
                    'country' => $country,
                    'requests' => $requestCount,
                    'x' => $coords[$country]['x'],
                    'y' => $coords[$country]['y'],
                    'size' => $size,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
