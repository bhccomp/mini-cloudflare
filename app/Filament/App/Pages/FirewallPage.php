<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Services\Aws\AwsFirewallInsightsService;
use App\Services\Bunny\BunnyFirewallInsightsService;

class FirewallPage extends BaseProtectionPage
{
    public string $eventCountry = '';

    public string $eventAction = '';

    public int $eventsPage = 1;

    public int $eventsPerPage = 20;
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

        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            return app(BunnyFirewallInsightsService::class)->getSiteInsights($this->site);
        }

        return app(AwsFirewallInsightsService::class)->getSiteInsights($this->site);
    }

    public function refreshFirewallInsights(): void
    {
        if (! $this->site) {
            return;
        }

        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            app(BunnyFirewallInsightsService::class)->forgetSiteInsightsCache($this->site->id);
        } else {
            app(AwsFirewallInsightsService::class)->forgetSiteInsightsCache($this->site->id);
        }

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
        $blockRatio = (float) data_get($insights, 'summary.block_ratio', 0);
        $suspiciousRatio = min(80, round($blockRatio * 0.8, 2));

        return collect($countries)
            ->map(function (array $row) use ($coords, $max, $blockRatio, $suspiciousRatio): ?array {
                $country = strtoupper((string) ($row['country'] ?? ''));
                $requestCount = (int) ($row['requests'] ?? 0);

                if (! isset($coords[$country])) {
                    return null;
                }

                $size = 7 + (int) round(($requestCount / $max) * 18);

                return [
                    'country' => $country,
                    'requests' => $requestCount,
                    'blocked_pct' => $blockRatio,
                    'suspicious_pct' => $suspiciousRatio,
                    'x' => $coords[$country]['x'],
                    'y' => $coords[$country]['y'],
                    'size' => $size,
                    'intensity' => round(($requestCount / $max) * 100),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function threatLevel(): string
    {
        $ratio = (float) data_get($this->firewallInsights(), 'summary.block_ratio', 0);

        return match (true) {
            $ratio >= 30 => 'Critical',
            $ratio >= 12 => 'Warning',
            default => 'Healthy',
        };
    }

    public function suspiciousRequests(): int
    {
        $total = (int) data_get($this->firewallInsights(), 'summary.total', 0);
        $blocked = (int) data_get($this->firewallInsights(), 'summary.blocked', 0);

        return max(0, (int) round(($total - $blocked) * 0.12));
    }

    public function filteredFirewallEvents(): array
    {
        $events = collect((array) data_get($this->firewallInsights(), 'events', []));

        if ($this->eventCountry !== '') {
            $events = $events->where('country', strtoupper($this->eventCountry));
        }

        if ($this->eventAction !== '') {
            $events = $events->filter(fn (array $row): bool => strtoupper((string) ($row['action'] ?? '')) === strtoupper($this->eventAction));
        }

        return $events
            ->slice(($this->eventsPage - 1) * $this->eventsPerPage, $this->eventsPerPage)
            ->values()
            ->all();
    }

    public function eventCountries(): array
    {
        return collect((array) data_get($this->firewallInsights(), 'events', []))
            ->pluck('country')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function nextEventsPage(): void
    {
        $this->eventsPage++;
    }

    public function prevEventsPage(): void
    {
        $this->eventsPage = max(1, $this->eventsPage - 1);
    }
}
