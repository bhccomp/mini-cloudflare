<?php

namespace App\Services\Firewall;

use App\Models\Site;
use App\Services\Bunny\BunnyGlobalDefaultsService;
use App\Services\Bunny\BunnyShieldSecurityService;
use App\Services\Bunny\Waf\BunnyShieldWafService;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FirewallPresetService
{
    public function __construct(
        protected EdgeProviderManager $providers,
        protected BunnyGlobalDefaultsService $globalDefaults,
        protected BunnyShieldSecurityService $shieldSecurity,
        protected BunnyShieldWafService $waf,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function presets(): array
    {
        return [
            [
                'id' => 'woocommerce_protected',
                'name' => 'WooCommerce Protected',
                'description' => 'Adds store-safe cache bypasses, checkout and account rate limits, and balanced bot detection for normal store traffic.',
                'highlights' => [
                    'Keeps cart, checkout, account, and Woo API traffic out of cache.',
                    'Adds WooCommerce-focused burst controls without touching unrelated paths.',
                    'Turns on balanced bot detection with deeper browser verification.',
                ],
            ],
            [
                'id' => 'high_bot_pressure',
                'name' => 'High Bot Pressure',
                'description' => 'Tightens WooCommerce protection when stores are already seeing bot swarms, scraping, or checkout/login pressure.',
                'highlights' => [
                    'Raises bot-detection sensitivity and enables stricter reputation signals.',
                    'Tightens WooCommerce API, account, cart, and checkout burst ceilings.',
                    'Blocks more hostile network classes while keeping store-safe cache bypasses.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applyPreset(Site $site, string $presetId): array
    {
        if ($site->provider !== Site::PROVIDER_BUNNY) {
            throw new \RuntimeException('Protection presets are currently available only for Standard Edge sites.');
        }

        $preset = $this->presetDefinition($presetId);
        if ($preset === null) {
            throw new \RuntimeException('Unknown protection preset.');
        }

        $cacheRuleCount = $this->applyCacheExclusions($site, $preset['cache_exclusions']);
        $rateLimitCount = $this->applyRateLimits($site, $presetId, $preset['rate_limits']);
        $this->applyShieldSettings($site, $preset['shield']);
        $this->applyBotDetection($site, $preset['bot_detection']);
        $this->persistPresetState($site->fresh(), $preset);

        return [
            'preset' => $preset['id'],
            'name' => $preset['name'],
            'cache_rule_count' => $cacheRuleCount,
            'rate_limit_count' => $rateLimitCount,
            'message' => "{$preset['name']} applied. Store cache bypasses, bot detection, and rate limits are now in place.",
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function presetDefinition(string $presetId): ?array
    {
        return collect($this->presetPayloads())->firstWhere('id', $presetId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function presetPayloads(): array
    {
        return [
            [
                'id' => 'woocommerce_protected',
                'name' => 'WooCommerce Protected',
                'cache_exclusions' => [
                    ['path_pattern' => '/cart*', 'reason' => 'Keep WooCommerce cart requests uncached and unoptimized.'],
                    ['path_pattern' => '/checkout*', 'reason' => 'Keep WooCommerce checkout traffic uncached and unoptimized.'],
                    ['path_pattern' => '/my-account*', 'reason' => 'Keep WooCommerce account traffic uncached and unoptimized.'],
                    ['path_pattern' => '/wc-api/*', 'reason' => 'Keep WooCommerce API endpoints uncached and unoptimized.'],
                    ['path_pattern' => '/wp-json/wc/*', 'reason' => 'Keep WooCommerce REST API traffic uncached and unoptimized.'],
                    ['path_pattern' => '/wp-json/wc/store/*', 'reason' => 'Keep WooCommerce Store API traffic uncached and unoptimized.'],
                ],
                'rate_limits' => [
                    [
                        'slug' => 'woo-checkout-burst',
                        'name' => 'WooCommerce checkout burst control',
                        'description' => 'Challenges suspicious checkout bursts before they overload store workflows.',
                        'action' => 'challenge',
                        'window_seconds' => 60,
                        'requests' => 60,
                        'path_pattern' => '/checkout*',
                    ],
                    [
                        'slug' => 'woo-cart-burst',
                        'name' => 'WooCommerce cart burst control',
                        'description' => 'Slows down repeated cart spikes without affecting the rest of the site.',
                        'action' => 'challenge',
                        'window_seconds' => 60,
                        'requests' => 180,
                        'path_pattern' => '/cart*',
                    ],
                    [
                        'slug' => 'woo-account-burst',
                        'name' => 'WooCommerce account burst control',
                        'description' => 'Challenges abusive account and sign-in pressure on customer account paths.',
                        'action' => 'challenge',
                        'window_seconds' => 60,
                        'requests' => 40,
                        'path_pattern' => '/my-account*',
                    ],
                    [
                        'slug' => 'woo-rest-api-burst',
                        'name' => 'WooCommerce API burst control',
                        'description' => 'Blocks aggressive bursts against WooCommerce REST API endpoints.',
                        'action' => 'block',
                        'window_seconds' => 10,
                        'requests' => 150,
                        'path_pattern' => '/wp-json/wc/*',
                    ],
                    [
                        'slug' => 'woo-store-api-burst',
                        'name' => 'WooCommerce store API burst control',
                        'description' => 'Challenges suspicious traffic bursts against the WooCommerce Store API.',
                        'action' => 'challenge',
                        'window_seconds' => 10,
                        'requests' => 400,
                        'path_pattern' => '/wp-json/wc/store/*',
                    ],
                ],
                'shield' => [
                    'waf_enabled' => true,
                    'waf_sensitivity' => 'high',
                    'ddos_sensitivity' => 'medium',
                    'challenge_window_minutes' => 30,
                    'learning_mode' => false,
                    'block_vpn' => false,
                    'block_tor' => false,
                    'block_datacentre' => false,
                ],
                'bot_detection' => [
                    'enabled' => true,
                    'request_integrity_sensitivity' => 2,
                    'ip_reputation_sensitivity' => 2,
                    'browser_fingerprint_sensitivity' => 2,
                    'browser_fingerprint_aggression' => 2,
                    'complex_fingerprinting' => true,
                ],
            ],
            [
                'id' => 'high_bot_pressure',
                'name' => 'High Bot Pressure',
                'cache_exclusions' => [
                    ['path_pattern' => '/cart*', 'reason' => 'Keep WooCommerce cart requests uncached and unoptimized.'],
                    ['path_pattern' => '/checkout*', 'reason' => 'Keep WooCommerce checkout traffic uncached and unoptimized.'],
                    ['path_pattern' => '/my-account*', 'reason' => 'Keep WooCommerce account traffic uncached and unoptimized.'],
                    ['path_pattern' => '/wc-api/*', 'reason' => 'Keep WooCommerce API endpoints uncached and unoptimized.'],
                    ['path_pattern' => '/wp-json/wc/*', 'reason' => 'Keep WooCommerce REST API traffic uncached and unoptimized.'],
                    ['path_pattern' => '/wp-json/wc/store/*', 'reason' => 'Keep WooCommerce Store API traffic uncached and unoptimized.'],
                ],
                'rate_limits' => [
                    [
                        'slug' => 'woo-checkout-burst',
                        'name' => 'WooCommerce checkout burst control',
                        'description' => 'Challenges tight checkout bursts when bots are pushing payment and checkout flows.',
                        'action' => 'challenge',
                        'window_seconds' => 60,
                        'requests' => 30,
                        'path_pattern' => '/checkout*',
                    ],
                    [
                        'slug' => 'woo-cart-burst',
                        'name' => 'WooCommerce cart burst control',
                        'description' => 'Challenges repeated cart spikes during active bot pressure.',
                        'action' => 'challenge',
                        'window_seconds' => 60,
                        'requests' => 120,
                        'path_pattern' => '/cart*',
                    ],
                    [
                        'slug' => 'woo-account-burst',
                        'name' => 'WooCommerce account burst control',
                        'description' => 'Challenges tighter account bursts when sign-in and account flows are under pressure.',
                        'action' => 'challenge',
                        'window_seconds' => 60,
                        'requests' => 20,
                        'path_pattern' => '/my-account*',
                    ],
                    [
                        'slug' => 'woo-rest-api-burst',
                        'name' => 'WooCommerce API burst control',
                        'description' => 'Blocks concentrated WooCommerce REST API bursts during active attack conditions.',
                        'action' => 'block',
                        'window_seconds' => 10,
                        'requests' => 100,
                        'path_pattern' => '/wp-json/wc/*',
                    ],
                    [
                        'slug' => 'woo-store-api-burst',
                        'name' => 'WooCommerce store API burst control',
                        'description' => 'Challenges heavier Store API bursts during active bot pressure.',
                        'action' => 'challenge',
                        'window_seconds' => 10,
                        'requests' => 250,
                        'path_pattern' => '/wp-json/wc/store/*',
                    ],
                ],
                'shield' => [
                    'waf_enabled' => true,
                    'waf_sensitivity' => 'high',
                    'ddos_sensitivity' => 'high',
                    'challenge_window_minutes' => 60,
                    'learning_mode' => false,
                    'block_vpn' => true,
                    'block_tor' => true,
                    'block_datacentre' => true,
                ],
                'bot_detection' => [
                    'enabled' => true,
                    'request_integrity_sensitivity' => 3,
                    'ip_reputation_sensitivity' => 3,
                    'browser_fingerprint_sensitivity' => 3,
                    'browser_fingerprint_aggression' => 3,
                    'complex_fingerprinting' => true,
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{path_pattern:string,reason:string}>  $desiredRows
     */
    protected function applyCacheExclusions(Site $site, array $desiredRows): int
    {
        $current = collect($this->globalDefaults->cacheExclusionsForSite($site))
            ->mapWithKeys(fn (array $row): array => [
                trim((string) ($row['path_pattern'] ?? '')) => [
                    'path_pattern' => trim((string) ($row['path_pattern'] ?? '')),
                    'reason' => trim((string) ($row['reason'] ?? '')),
                    'enabled' => (bool) ($row['enabled'] ?? false),
                ],
            ]);

        foreach ($desiredRows as $row) {
            $pattern = trim((string) ($row['path_pattern'] ?? ''));
            if ($pattern === '') {
                continue;
            }

            $current[$pattern] = [
                'path_pattern' => $pattern,
                'reason' => trim((string) ($row['reason'] ?? '')),
                'enabled' => true,
            ];
        }

        $provider = $this->providers->forSite($site);
        $provider->applySiteControlSetting($site, 'cache_exclusions', $current->values()->all());

        return count($desiredRows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $desiredRules
     */
    protected function applyRateLimits(Site $site, string $presetId, array $desiredRules): int
    {
        $tracked = $this->trackedRateLimits($site, $presetId);
        $service = $this->shieldSecurity;
        $current = collect($service->presentRateLimits($site));
        $updatedTracking = [];

        foreach ($desiredRules as $rule) {
            $slug = (string) ($rule['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $trackedId = (string) ($tracked[$slug] ?? '');
            $existing = $current->first(function (array $row) use ($trackedId, $rule): bool {
                if ($trackedId !== '' && (string) ($row['id'] ?? '') === $trackedId) {
                    return true;
                }

                return (string) ($row['name'] ?? '') === (string) ($rule['name'] ?? '');
            });

            $state = Arr::only($rule, ['name', 'description', 'action', 'window_seconds', 'requests', 'path_pattern']);

            if (is_array($existing) && $existing !== []) {
                if ((bool) ($existing['is_live'] ?? false) && (string) ($existing['live_id'] ?? '') !== '') {
                    $service->updateRateLimit($site, (string) $existing['live_id'], $state);
                    $updatedTracking[$slug] = (string) $existing['id'];
                } else {
                    $service->deleteSavedDisabledRateLimit($site, (string) ($existing['id'] ?? ''));
                    $created = $service->createRateLimit($site, $state);
                    $updatedTracking[$slug] = (string) ($created['id'] ?? '');
                }
            } else {
                $created = $service->createRateLimit($site, $state);
                $updatedTracking[$slug] = (string) ($created['id'] ?? '');
            }

            $site->refresh();
            $current = collect($service->presentRateLimits($site));
        }

        foreach (array_diff(array_keys($tracked), array_keys($updatedTracking)) as $obsoleteSlug) {
            $obsoleteId = (string) ($tracked[$obsoleteSlug] ?? '');
            if ($obsoleteId === '') {
                continue;
            }

            $existing = $current->first(fn (array $row): bool => (string) ($row['id'] ?? '') === $obsoleteId);
            if (! is_array($existing) || $existing === []) {
                continue;
            }

            if ((bool) ($existing['is_live'] ?? false) && (string) ($existing['live_id'] ?? '') !== '') {
                $service->deleteRateLimit($site, (string) $existing['live_id']);
            } else {
                $service->deleteSavedDisabledRateLimit($site, (string) ($existing['id'] ?? ''));
            }
        }

        $this->persistTrackedRateLimits($site->fresh(), $presetId, $updatedTracking);

        return count($updatedTracking);
    }

    /**
     * @param  array<string, mixed>  $desired
     */
    protected function applyShieldSettings(Site $site, array $desired): void
    {
        $current = $this->shieldSecurity->currentSettings($site);

        $this->shieldSecurity->updateSettings($site, array_merge($current, $desired));
    }

    /**
     * @param  array<string, mixed>  $desired
     */
    protected function applyBotDetection(Site $site, array $desired): void
    {
        $current = $this->waf->botDetectionSettings($site);

        $this->waf->updateBotDetectionSettings($site, array_merge($current, $desired));
    }

    /**
     * @return array<string, string>
     */
    protected function trackedRateLimits(Site $site, string $presetId): array
    {
        $meta = data_get($site->provider_meta, "firewall_presets.{$presetId}.rate_limits", []);

        return is_array($meta)
            ? collect($meta)
                ->filter(fn (mixed $id, mixed $slug): bool => is_string($slug) && is_string($id) && $id !== '')
                ->map(fn (mixed $id): string => (string) $id)
                ->all()
            : [];
    }

    /**
     * @param  array<string, string>  $tracking
     */
    protected function persistTrackedRateLimits(Site $site, string $presetId, array $tracking): void
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        data_set($meta, "firewall_presets.{$presetId}.rate_limits", $tracking);
        $site->forceFill(['provider_meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $preset
     */
    protected function persistPresetState(Site $site, array $preset): void
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        data_set($meta, 'firewall_presets.last_applied', [
            'id' => (string) ($preset['id'] ?? ''),
            'name' => (string) ($preset['name'] ?? 'Protection preset'),
            'applied_at' => now()->toIso8601String(),
        ]);
        $site->forceFill(['provider_meta' => $meta])->save();
    }
}
