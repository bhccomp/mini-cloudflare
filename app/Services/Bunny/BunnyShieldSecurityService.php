<?php

namespace App\Services\Bunny;

use App\Models\Site;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BunnyShieldSecurityService
{
    public function __construct(
        protected BunnyApiService $api,
        protected BunnyShieldAccessListService $accessLists,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function currentSettings(Site $site): array
    {
        if ($site->isDemoSeeded()) {
            $saved = (array) data_get($site->provider_meta, 'shield_settings', []);

            return [
                'shield_zone_id' => 'demo-shield-zone',
                'waf_sensitivity' => $this->normalizeSensitivity((string) ($saved['waf_sensitivity'] ?? 'medium')),
                'ddos_sensitivity' => $this->normalizeSensitivity((string) ($saved['ddos_sensitivity'] ?? 'medium')),
                'bot_sensitivity' => $this->normalizeSensitivity((string) ($saved['bot_sensitivity'] ?? 'medium')),
                'challenge_window_minutes' => (int) ($saved['challenge_window_minutes'] ?? 30),
                'waf_enabled' => (bool) ($saved['waf_enabled'] ?? true),
                'premium_plan' => true,
                'plan_type' => 1,
                'whitelabel_response_pages' => true,
                'raw' => ['demo_seeded' => true],
            ];
        }

        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $response = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}");

        $payload = $response->successful() ? $this->normalizeEnvelope($response->json()) : [];
        $saved = (array) data_get($site->provider_meta, 'shield_settings', []);

        return [
            'shield_zone_id' => $shieldZoneId,
            'waf_sensitivity' => $this->normalizeSensitivity(
                Arr::get($saved, 'waf_sensitivity')
                    ?? Arr::get($payload, 'wafExecutionMode')
                    ?? Arr::get($payload, 'WafExecutionMode')
            ),
            'ddos_sensitivity' => $this->normalizeSensitivity(Arr::get($saved, 'ddos_sensitivity')),
            'bot_sensitivity' => $this->normalizeSensitivity(Arr::get($saved, 'bot_sensitivity')),
            'challenge_window_minutes' => (int) (Arr::get($saved, 'challenge_window_minutes', 30)),
            'waf_enabled' => (bool) (
                Arr::get($payload, 'wafEnabled')
                ?? Arr::get($payload, 'WafEnabled')
                ?? true
            ),
            'learning_mode' => (bool) (
                Arr::get($payload, 'learningMode')
                ?? Arr::get($payload, 'LearningMode')
                ?? false
            ),
            'block_vpn' => (bool) (
                Arr::get($payload, 'blockVpn')
                ?? Arr::get($payload, 'BlockVpn')
                ?? false
            ),
            'block_tor' => (bool) (
                Arr::get($payload, 'blockTor')
                ?? Arr::get($payload, 'BlockTor')
                ?? false
            ),
            'block_datacentre' => (bool) (
                Arr::get($payload, 'blockDatacentre')
                ?? Arr::get($payload, 'BlockDatacentre')
                ?? false
            ),
            'premium_plan' => (bool) (
                Arr::get($payload, 'premiumPlan')
                ?? Arr::get($payload, 'PremiumPlan')
                ?? false
            ),
            'plan_type' => (int) (
                Arr::get($payload, 'planType')
                ?? Arr::get($payload, 'PlanType')
                ?? Arr::get($saved, 'plan_type', 0)
            ),
            'whitelabel_response_pages' => (bool) (
                Arr::get($payload, 'whitelabelResponsePages')
                ?? Arr::get($payload, 'WhitelabelResponsePages')
                ?? false
            ),
            'raw' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function updateSettings(Site $site, array $state): array
    {
        if ($site->isDemoSeeded()) {
            $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
            $meta['shield_settings'] = [
                'waf_sensitivity' => $this->normalizeSensitivity((string) ($state['waf_sensitivity'] ?? 'medium')),
                'ddos_sensitivity' => $this->normalizeSensitivity((string) ($state['ddos_sensitivity'] ?? 'medium')),
                'bot_sensitivity' => $this->normalizeSensitivity((string) ($state['bot_sensitivity'] ?? 'medium')),
                'challenge_window_minutes' => max(5, (int) ($state['challenge_window_minutes'] ?? 30)),
                'waf_enabled' => true,
                'updated_at' => now()->toIso8601String(),
            ];
            $site->forceFill(['provider_meta' => $meta])->save();

            return [
                'shield_zone_id' => 'demo-shield-zone',
                'updated' => true,
            ];
        }

        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $wafSensitivity = $this->normalizeSensitivity((string) ($state['waf_sensitivity'] ?? 'medium'));
        $ddosSensitivity = $this->normalizeSensitivity((string) ($state['ddos_sensitivity'] ?? 'medium'));
        $botSensitivity = $this->normalizeSensitivity((string) ($state['bot_sensitivity'] ?? 'medium'));
        $challengeWindow = max(5, (int) ($state['challenge_window_minutes'] ?? 30));
        $wafEnabled = (bool) ($state['waf_enabled'] ?? true);
        $learningMode = (bool) ($state['learning_mode'] ?? false);
        $blockVpn = (bool) ($state['block_vpn'] ?? false);
        $blockTor = (bool) ($state['block_tor'] ?? false);
        $blockDatacentre = (bool) ($state['block_datacentre'] ?? false);
        $current = $this->normalizeEnvelope($this->api->client()->get("/shield/shield-zone/{$shieldZoneId}")->json());

        $payload = $this->buildShieldZonePatchPayload($shieldZoneId, $current, [
            'wafEnabled' => $wafEnabled,
            'learningMode' => $learningMode,
            'wafExecutionMode' => $this->sensitivityToCode($wafSensitivity),
            'wafRealtimeThreatIntelligenceEnabled' => in_array($ddosSensitivity, ['high', 'extreme'], true),
            'wafProfileId' => $this->sensitivityToCode($wafSensitivity),
            'blockVpn' => $blockVpn,
            'blockTor' => $blockTor,
            'blockDatacentre' => $blockDatacentre,
            'whitelabelResponsePages' => true,
        ], $challengeWindow);

        $response = $this->api->client()->patch('/shield/shield-zone', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to update security settings.'));
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['shield_zone_id'] = $shieldZoneId;
        $meta['shield_settings'] = [
            'waf_sensitivity' => $wafSensitivity,
            'ddos_sensitivity' => $ddosSensitivity,
            'bot_sensitivity' => $botSensitivity,
            'challenge_window_minutes' => $challengeWindow,
            'waf_enabled' => $wafEnabled,
            'learning_mode' => $learningMode,
            'block_vpn' => $blockVpn,
            'block_tor' => $blockTor,
            'block_datacentre' => $blockDatacentre,
            'whitelabel_response_pages' => true,
            'updated_at' => now()->toIso8601String(),
        ];
        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'shield_zone_id' => $shieldZoneId,
            'updated' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureAdvancedPlan(Site $site, ?int $shieldZoneId = null): array
    {
        if ($site->isDemoSeeded()) {
            return [
                'shield_zone_id' => 'demo-shield-zone',
                'changed' => false,
                'message' => 'Demo dashboard uses a simulated Shield plan.',
            ];
        }

        $shieldZoneId ??= $this->accessLists->ensureShieldZone($site);
        $planType = (int) config('edge.bunny.shield_advanced_plan_type', 0);

        $currentResponse = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}");
        if (! $currentResponse->successful()) {
            throw new \RuntimeException($this->responseError($currentResponse, 'Unable to load Bunny Shield plan details.'));
        }

        $current = $this->normalizeEnvelope($currentResponse->json());
        $premiumPlan = (bool) (
            Arr::get($current, 'premiumPlan')
            ?? Arr::get($current, 'PremiumPlan')
            ?? false
        );
        $currentPlanType = (int) (
            Arr::get($current, 'planType')
            ?? Arr::get($current, 'PlanType')
            ?? 0
        );
        $currentWhitelabel = (bool) (
            Arr::get($current, 'whitelabelResponsePages')
            ?? Arr::get($current, 'WhitelabelResponsePages')
            ?? false
        );

        if ($this->isAdvancedPlanState($premiumPlan, $currentPlanType, $currentWhitelabel, $planType)) {
            return [
                'shield_zone_id' => $shieldZoneId,
                'changed' => false,
                'message' => 'Bunny Shield advanced plan is already enabled.',
            ];
        }

        $payload = $this->buildShieldZonePatchPayload($shieldZoneId, $current, [
            'premiumPlan' => true,
            'planType' => $planType,
            'whitelabelResponsePages' => true,
        ]);

        $response = $this->api->client()->patch('/shield/shield-zone', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to enable Bunny Shield advanced plan.'));
        }

        $verified = $this->waitForAdvancedPlan($shieldZoneId, $planType);

        if (! $this->isAdvancedPlanState(
            (bool) ($verified['premium_plan'] ?? false),
            (int) ($verified['plan_type'] ?? 0),
            (bool) ($verified['whitelabel_response_pages'] ?? false),
            $planType,
        )) {
            throw new \RuntimeException('Bunny Shield advanced plan is still being applied. The requested advanced plan type and white-label response pages are not active yet.');
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['shield_plan'] = 'advanced';
        $meta['shield_premium_plan'] = true;
        $meta['shield_plan_type'] = $planType;
        $meta['shield_whitelabel_response_pages'] = true;
        $meta['shield_plan_upgraded_at'] = now()->toIso8601String();
        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'shield_zone_id' => $shieldZoneId,
            'changed' => true,
            'message' => 'Bunny Shield advanced plan enabled.',
        ];
    }

    /**
     * @return array{premium_plan: bool, whitelabel_response_pages: bool, plan_type: int}
     */
    protected function waitForAdvancedPlan(int $shieldZoneId, int $planType, int $attempts = 6, int $sleepSeconds = 2): array
    {
        $last = [
            'premium_plan' => false,
            'whitelabel_response_pages' => false,
            'plan_type' => 0,
        ];

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $response = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}");

            if ($response->successful()) {
                $current = $this->normalizeEnvelope($response->json());
                $last = [
                    'premium_plan' => (bool) (
                        Arr::get($current, 'premiumPlan')
                        ?? Arr::get($current, 'PremiumPlan')
                        ?? false
                    ),
                    'whitelabel_response_pages' => (bool) (
                        Arr::get($current, 'whitelabelResponsePages')
                        ?? Arr::get($current, 'WhitelabelResponsePages')
                        ?? false
                    ),
                    'plan_type' => (int) (
                        Arr::get($current, 'planType')
                        ?? Arr::get($current, 'PlanType')
                        ?? 0
                    ),
                ];

                if ($this->isAdvancedPlanState(
                    (bool) ($last['premium_plan'] ?? false),
                    (int) ($last['plan_type'] ?? 0),
                    (bool) ($last['whitelabel_response_pages'] ?? false),
                    $planType,
                )) {
                    return $last;
                }
            }

            if ($attempt < $attempts - 1) {
                sleep($sleepSeconds);
            }
        }

        return $last;
    }

    /**
     * @return array<string, mixed>
     */
    public function setTroubleshootingMode(Site $site, bool $enabled, ?bool $restoreWafEnabled = null): array
    {
        if ($site->isDemoSeeded()) {
            $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
            $meta['shield_settings'] = array_merge((array) ($meta['shield_settings'] ?? []), [
                'waf_enabled' => ! $enabled,
                'troubleshooting_mode' => $enabled,
                'updated_at' => now()->toIso8601String(),
            ]);
            $site->forceFill([
                'provider_meta' => $meta,
                'troubleshooting_mode' => $enabled,
            ])->save();

            return [
                'shield_zone_id' => 'demo-shield-zone',
                'changed' => true,
                'waf_enabled' => ! $enabled,
                'message' => $enabled ? 'Demo troubleshooting mode enabled.' : 'Demo troubleshooting mode disabled.',
            ];
        }

        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $currentResponse = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}");

        if (! $currentResponse->successful()) {
            throw new \RuntimeException($this->responseError($currentResponse, 'Unable to load Bunny Shield settings.'));
        }

        $current = $this->normalizeEnvelope($currentResponse->json());
        $targetWafEnabled = $enabled ? false : ($restoreWafEnabled ?? true);
        $currentWafEnabled = (bool) (
            Arr::get($current, 'wafEnabled')
            ?? Arr::get($current, 'WafEnabled')
            ?? true
        );

        if ($currentWafEnabled === $targetWafEnabled) {
            return [
                'shield_zone_id' => $shieldZoneId,
                'changed' => false,
                'waf_enabled' => $targetWafEnabled,
                'message' => $enabled
                    ? 'Shield WAF is already disabled for troubleshooting.'
                    : 'Shield WAF is already restored.',
            ];
        }

        $payload = $this->buildShieldZonePatchPayload($shieldZoneId, $current, [
            'wafEnabled' => $targetWafEnabled,
        ]);

        $response = $this->api->client()->patch('/shield/shield-zone', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to update Bunny Shield troubleshooting state.'));
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['shield_settings'] = array_merge((array) ($meta['shield_settings'] ?? []), [
            'waf_enabled' => $targetWafEnabled,
            'troubleshooting_mode' => $enabled,
            'updated_at' => now()->toIso8601String(),
        ]);
        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'shield_zone_id' => $shieldZoneId,
            'changed' => true,
            'waf_enabled' => $targetWafEnabled,
            'message' => $enabled
                ? 'Shield WAF disabled for troubleshooting.'
                : 'Shield WAF restored.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function downgradePlan(Site $site, ?int $shieldZoneId = null): array
    {
        $shieldZoneId ??= (int) data_get($site->provider_meta, 'shield_zone_id', 0);
        if ($shieldZoneId <= 0) {
            return [
                'shield_zone_id' => 0,
                'changed' => false,
                'message' => 'No Bunny Shield zone is linked to this site.',
            ];
        }

        $currentResponse = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}");
        if (! $currentResponse->successful()) {
            throw new \RuntimeException($this->responseError($currentResponse, 'Unable to load Bunny Shield plan details.'));
        }

        $current = $this->normalizeEnvelope($currentResponse->json());
        $premiumPlan = (bool) (
            Arr::get($current, 'premiumPlan')
            ?? Arr::get($current, 'PremiumPlan')
            ?? false
        );
        $currentPlanType = (int) (
            Arr::get($current, 'planType')
            ?? Arr::get($current, 'PlanType')
            ?? 0
        );
        $currentWhitelabel = (bool) (
            Arr::get($current, 'whitelabelResponsePages')
            ?? Arr::get($current, 'WhitelabelResponsePages')
            ?? false
        );

        if (! $this->isAnyAdvancedPlanState($premiumPlan, $currentPlanType, $currentWhitelabel)) {
            return [
                'shield_zone_id' => $shieldZoneId,
                'changed' => false,
                'message' => 'Bunny Shield premium plan is already disabled.',
            ];
        }

        $payload = $this->buildShieldZonePatchPayload($shieldZoneId, $current, [
            'premiumPlan' => false,
            'planType' => 0,
            'whitelabelResponsePages' => false,
        ]);

        $response = $this->api->client()->patch('/shield/shield-zone', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to downgrade Bunny Shield plan.'));
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['shield_plan'] = 'basic';
        $meta['shield_premium_plan'] = false;
        $meta['shield_plan_downgraded_at'] = now()->toIso8601String();
        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'shield_zone_id' => $shieldZoneId,
            'changed' => true,
            'message' => 'Bunny Shield premium plan downgraded.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function currentPlanState(int $shieldZoneId): array
    {
        if ($shieldZoneId <= 0) {
            return [
                'exists' => false,
                'premium_plan' => false,
                'plan_type' => 0,
                'raw' => [],
            ];
        }

        $response = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}");

        if (in_array($response->status(), [404, 410], true)) {
            return [
                'exists' => false,
                'premium_plan' => false,
                'plan_type' => 0,
                'raw' => [],
            ];
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to verify Bunny Shield plan state.'));
        }

        $current = $this->normalizeEnvelope($response->json());

        return [
            'exists' => true,
            'premium_plan' => (bool) (
                Arr::get($current, 'premiumPlan')
                ?? Arr::get($current, 'PremiumPlan')
                ?? false
            ),
            'whitelabel_response_pages' => (bool) (
                Arr::get($current, 'whitelabelResponsePages')
                ?? Arr::get($current, 'WhitelabelResponsePages')
                ?? false
            ),
            'plan_type' => (int) (
                Arr::get($current, 'planType')
                ?? Arr::get($current, 'PlanType')
                ?? 0
            ),
            'raw' => $current,
        ];
    }

    protected function isAdvancedPlanState(bool $premiumPlan, int $planType, bool $whitelabelResponsePages, int $expectedPlanType): bool
    {
        return $planType === $expectedPlanType && $whitelabelResponsePages && ($premiumPlan || $planType > 0);
    }

    protected function isAnyAdvancedPlanState(bool $premiumPlan, int $planType, bool $whitelabelResponsePages): bool
    {
        return $whitelabelResponsePages && ($premiumPlan || $planType > 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRateLimits(Site $site): array
    {
        if ($site->isDemoSeeded()) {
            return (array) data_get($site->provider_meta, 'demo_rate_limits', []);
        }

        $shieldZoneId = $this->accessLists->ensureShieldZone($site);

        $responses = [
            $this->api->client()->get("/shield/rate-limits/{$shieldZoneId}"),
            $this->api->client()->get('/shield/rate-limit', ['shieldZoneId' => $shieldZoneId]),
            $this->api->client()->get('/shield/rate-limits', ['shieldZoneId' => $shieldZoneId]),
        ];

        foreach ($responses as $response) {
            if (! $response->successful()) {
                continue;
            }

            $rows = $this->extractRows($response->json());
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function presentRateLimits(Site $site): array
    {
        $visibleLive = app(BunnyGlobalDefaultsService::class)->filterVisibleRateLimits($this->listRateLimits($site));
        $displayMeta = $this->rateLimitDisplayMeta($site);

        $live = collect($visibleLive)
            ->map(fn (array $rule): array => $this->applyRateLimitDisplayMeta(
                $this->normalizeRateLimitRule($rule, true),
                $displayMeta
            ))
            ->filter(fn (array $rule): bool => $rule['name'] !== '')
            ->values();

        $disabled = collect($this->savedDisabledRateLimits($site))
            ->map(fn (array $rule): array => $this->normalizeSavedRateLimitRule($rule))
            ->filter(fn (array $rule): bool => $rule['name'] !== '')
            ->values();

        return $live
            ->concat($disabled)
            ->sortBy([
                ['enabled', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function createRateLimit(Site $site, array $state): array
    {
        if ($site->isDemoSeeded()) {
            $rules = collect((array) data_get($site->provider_meta, 'demo_rate_limits', []));
            $rules->prepend([
                'id' => 'demo-'.\Illuminate\Support\Str::uuid(),
                'Name' => (string) ($state['name'] ?? 'Rate limit'),
                'Description' => (string) ($state['description'] ?? ''),
                'Enabled' => true,
                'ActionType' => strtolower((string) ($state['action'] ?? 'block')),
                'RuleConfiguration' => [
                    'windowSeconds' => max(1, (int) ($state['window_seconds'] ?? 10)),
                    'requestLimit' => max(1, (int) ($state['requests'] ?? 100)),
                    'pathPattern' => trim((string) ($state['path_pattern'] ?? '')),
                ],
            ]);

            $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
            $meta['demo_rate_limits'] = $rules->take(20)->values()->all();
            $site->forceFill(['provider_meta' => $meta])->save();

            return [
                'id' => (string) data_get($meta, 'demo_rate_limits.0.id', 'demo-rate-limit'),
                'response' => ['demo_seeded' => true],
            ];
        }

        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $payload = $this->buildRateLimitPayload($shieldZoneId, $state);
        $response = $this->api->client()->post('/shield/rate-limit', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to create rate limit rule.'));
        }

        $normalized = $this->normalizeEnvelope($response->json());
        $id = (string) (Arr::get($normalized, 'id') ?? Arr::get($normalized, 'Id') ?? '');

        if ($id !== '') {
            $this->persistRateLimitDisplayMeta($site, $id, [
                'name' => (string) ($state['name'] ?? 'Rate limit'),
                'description' => (string) ($state['description'] ?? ''),
            ]);
        }

        return [
            'id' => $id,
            'response' => $normalized,
        ];
    }

    public function deleteRateLimit(Site $site, string $id): void
    {
        if ($site->isDemoSeeded() || trim($id) === '') {
            return;
        }

        $responses = [
            $this->api->client()->delete('/shield/rate-limit/'.$id),
            $this->api->client()->delete('/shield/rate-limits/'.$id),
        ];

        foreach ($responses as $response) {
            if ($response->successful() || in_array($response->status(), [404, 410], true)) {
                $this->forgetRateLimitDisplayMeta($site, trim($id));

                return;
            }
        }

        throw new \RuntimeException('Unable to delete rate limit rule.');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function updateRateLimit(Site $site, string $id, array $state): void
    {
        if ($site->isDemoSeeded() || trim($id) === '') {
            return;
        }

        $payload = $this->buildRateLimitPatchPayload($state);
        $response = $this->api->client()->patch('/shield/rate-limit/'.trim($id), $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to update rate limit rule.'));
        }

        $this->persistRateLimitDisplayMeta($site, trim($id), [
            'name' => (string) ($state['name'] ?? 'Rate limit'),
            'description' => (string) ($state['description'] ?? ''),
        ]);
    }

    public function disableRateLimit(Site $site, string $id): void
    {
        $rule = collect($this->presentRateLimits($site))
            ->first(fn (array $row): bool => (string) ($row['id'] ?? '') === trim($id) && (bool) ($row['enabled'] ?? false));

        if (! is_array($rule) || $rule === []) {
            throw new \RuntimeException('Unable to find the selected rate limit rule.');
        }

        if (! (bool) ($rule['is_live'] ?? false) || (string) ($rule['live_id'] ?? '') === '') {
            throw new \RuntimeException('Only active edge rules can be disabled.');
        }

        $disabled = collect($this->savedDisabledRateLimits($site))
            ->reject(fn (array $row): bool => (string) ($row['id'] ?? '') === (string) $rule['id'])
            ->push([
                'id' => (string) $rule['id'],
                'name' => (string) $rule['name'],
                'description' => (string) ($rule['description'] ?? ''),
                'action' => (string) $rule['action'],
                'window_seconds' => (int) $rule['window_seconds'],
                'requests' => (int) $rule['requests'],
                'path_pattern' => (string) ($rule['path_pattern'] ?? ''),
                'enabled' => false,
                'saved_at' => now()->toIso8601String(),
            ])
            ->values()
            ->all();

        $this->deleteRateLimit($site, (string) $rule['live_id']);
        $this->persistSavedDisabledRateLimits($site, $disabled);
    }

    public function enableRateLimit(Site $site, string $id): void
    {
        $disabled = collect($this->savedDisabledRateLimits($site));
        $rule = $disabled->first(fn (array $row): bool => (string) ($row['id'] ?? '') === trim($id));

        if (! is_array($rule) || $rule === []) {
            throw new \RuntimeException('Unable to find the saved disabled rate limit rule.');
        }

        $this->createRateLimit($site, [
            'name' => (string) ($rule['name'] ?? 'Rate limit'),
            'description' => (string) ($rule['description'] ?? ''),
            'action' => (string) ($rule['action'] ?? 'block'),
            'window_seconds' => (int) ($rule['window_seconds'] ?? 10),
            'requests' => (int) ($rule['requests'] ?? 100),
            'path_pattern' => (string) ($rule['path_pattern'] ?? ''),
        ]);

        $this->persistSavedDisabledRateLimits(
            $site,
            $disabled
                ->reject(fn (array $row): bool => (string) ($row['id'] ?? '') === trim($id))
                ->values()
                ->all()
        );
    }

    public function deleteSavedDisabledRateLimit(Site $site, string $id): void
    {
        $this->persistSavedDisabledRateLimits(
            $site,
            collect($this->savedDisabledRateLimits($site))
                ->reject(fn (array $row): bool => (string) ($row['id'] ?? '') === trim($id))
                ->values()
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function updateSavedDisabledRateLimit(Site $site, string $id, array $state): void
    {
        $updated = collect($this->savedDisabledRateLimits($site))
            ->map(function (array $row) use ($id, $state): array {
                if ((string) ($row['id'] ?? '') !== trim($id)) {
                    return $row;
                }

                return array_merge($row, [
                    'name' => (string) ($state['name'] ?? $row['name'] ?? 'Rate limit'),
                    'description' => (string) ($state['description'] ?? $row['description'] ?? ''),
                    'action' => (string) ($state['action'] ?? $row['action'] ?? 'block'),
                    'window_seconds' => (int) ($state['window_seconds'] ?? $row['window_seconds'] ?? 10),
                    'requests' => (int) ($state['requests'] ?? $row['requests'] ?? 100),
                    'path_pattern' => (string) ($state['path_pattern'] ?? $row['path_pattern'] ?? ''),
                    'saved_at' => now()->toIso8601String(),
                ]);
            })
            ->values()
            ->all();

        $this->persistSavedDisabledRateLimits($site, $updated);
    }

    public function sensitivityOptions(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'extreme' => 'Extreme',
        ];
    }

    public function challengeWindowOptions(): array
    {
        return [
            5 => '5 minutes',
            10 => '10 minutes',
            30 => '30 minutes',
            60 => '1 hour',
            180 => '3 hours',
            720 => '12 hours',
            1440 => '24 hours',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeEnvelope(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $data = Arr::get($payload, 'data');
        if (is_array($data)) {
            return $data;
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractRows(mixed $payload): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return $payload;
        }

        if (! is_array($payload)) {
            return [];
        }

        foreach (['Items', 'items', 'data', 'records', 'Records'] as $key) {
            $rows = $payload[$key] ?? null;
            if (is_array($rows) && array_is_list($rows)) {
                return $rows;
            }
        }

        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            return $payload['data'];
        }

        return [];
    }

    protected function normalizeSensitivity(mixed $value): string
    {
        if (is_numeric($value)) {
            return match ((int) $value) {
                0 => 'low',
                1 => 'medium',
                2 => 'high',
                default => 'extreme',
            };
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'low', 'medium', 'high', 'extreme' => $normalized,
            default => 'medium',
        };
    }

    protected function sensitivityToCode(string $value): int
    {
        return match ($this->normalizeSensitivity($value)) {
            'low' => 0,
            'medium' => 1,
            'high' => 2,
            'extreme' => 3,
            default => 1,
        };
    }

    protected function actionToCode(string $action): int
    {
        return match (strtolower($action)) {
            'challenge' => 2,
            default => 1,
        };
    }

    protected function actionLabel(mixed $action): string
    {
        if (is_numeric($action)) {
            return match ((int) $action) {
                0 => 'allow',
                2 => 'challenge',
                default => 'block',
            };
        }

        $normalized = strtolower(trim((string) $action));

        return in_array($normalized, ['allow', 'block', 'challenge'], true)
            ? $normalized
            : 'block';
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    protected function normalizeRateLimitRule(array $rule, bool $live): array
    {
        $config = (array) (Arr::get($rule, 'RuleConfiguration') ?? Arr::get($rule, 'ruleConfiguration') ?? []);
        $id = (string) (Arr::get($rule, 'id') ?? Arr::get($rule, 'Id') ?? '');
        $name = (string) (Arr::get($rule, 'ruleName') ?? Arr::get($rule, 'RuleName') ?? Arr::get($rule, 'name') ?? Arr::get($rule, 'Name') ?? '');
        $description = (string) (Arr::get($rule, 'ruleDescription') ?? Arr::get($rule, 'RuleDescription') ?? Arr::get($rule, 'description') ?? Arr::get($rule, 'Description') ?? '');
        $pathPattern = trim((string) ($config['value'] ?? $config['pathPattern'] ?? $config['path_pattern'] ?? ''));

        return [
            'id' => $id !== '' ? $id : strtolower($name),
            'live_id' => $live ? $id : null,
            'name' => $name,
            'description' => $description,
            'action' => $this->actionLabel(Arr::get($rule, 'actionType') ?? Arr::get($rule, 'ActionType')),
            'window_seconds' => (int) ($config['timeframe'] ?? $config['windowSeconds'] ?? $config['window_seconds'] ?? 0),
            'requests' => (int) ($config['requestCount'] ?? $config['requestLimit'] ?? $config['request_limit'] ?? 0),
            'path_pattern' => $pathPattern,
            'enabled' => (bool) (Arr::get($rule, 'enabled') ?? Arr::get($rule, 'Enabled') ?? true),
            'is_live' => $live,
            'source' => $live ? 'edge' : 'saved',
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    protected function normalizeSavedRateLimitRule(array $rule): array
    {
        return [
            'id' => (string) ($rule['id'] ?? ''),
            'live_id' => null,
            'name' => (string) ($rule['name'] ?? ''),
            'description' => (string) ($rule['description'] ?? ''),
            'action' => $this->actionLabel($rule['action'] ?? 'block'),
            'window_seconds' => (int) ($rule['window_seconds'] ?? 0),
            'requests' => (int) ($rule['requests'] ?? 0),
            'path_pattern' => trim((string) ($rule['path_pattern'] ?? '')),
            'enabled' => false,
            'is_live' => false,
            'source' => 'saved',
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, array{name:string,description:string}>  $displayMeta
     * @return array<string, mixed>
     */
    protected function applyRateLimitDisplayMeta(array $rule, array $displayMeta): array
    {
        $id = (string) ($rule['id'] ?? '');

        if ($id === '' || ! isset($displayMeta[$id]) || ! is_array($displayMeta[$id])) {
            return $rule;
        }

        $display = $displayMeta[$id];
        $rule['name'] = (string) ($display['name'] ?? $rule['name'] ?? 'Rate limit');
        $rule['description'] = (string) ($display['description'] ?? $rule['description'] ?? '');

        return $rule;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function savedDisabledRateLimits(Site $site): array
    {
        return collect((array) data_get($site->provider_meta, 'saved_disabled_rate_limits', []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    protected function persistSavedDisabledRateLimits(Site $site, array $rules): void
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['saved_disabled_rate_limits'] = array_values($rules);
        $site->forceFill(['provider_meta' => $meta])->save();
    }

    /**
     * @return array<string, array{name:string,description:string}>
     */
    protected function rateLimitDisplayMeta(Site $site): array
    {
        $meta = data_get($site->provider_meta, 'rate_limit_display_meta', []);

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param  array{name:string,description:string}  $display
     */
    protected function persistRateLimitDisplayMeta(Site $site, string $id, array $display): void
    {
        if ($id === '') {
            return;
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $displayMeta = $this->rateLimitDisplayMeta($site);
        $displayMeta[$id] = [
            'name' => (string) ($display['name'] ?? 'Rate limit'),
            'description' => (string) ($display['description'] ?? ''),
        ];
        $meta['rate_limit_display_meta'] = $displayMeta;
        $site->forceFill(['provider_meta' => $meta])->save();
    }

    protected function forgetRateLimitDisplayMeta(Site $site, string $id): void
    {
        if ($id === '') {
            return;
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $displayMeta = $this->rateLimitDisplayMeta($site);
        unset($displayMeta[$id]);
        $meta['rate_limit_display_meta'] = $displayMeta;
        $site->forceFill(['provider_meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function buildRateLimitPayload(int $shieldZoneId, array $state): array
    {
        return [
            'shieldZoneId' => $shieldZoneId,
            'ruleName' => $this->sanitizeRateLimitText((string) ($state['name'] ?? 'Rate limit')),
            'ruleDescription' => $this->sanitizeRateLimitText((string) ($state['description'] ?? '')),
            'ruleConfiguration' => $this->buildRateLimitConfiguration($state),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function buildRateLimitPatchPayload(array $state): array
    {
        return [
            'ruleName' => $this->sanitizeRateLimitText((string) ($state['name'] ?? 'Rate limit')),
            'ruleDescription' => $this->sanitizeRateLimitText((string) ($state['description'] ?? '')),
            'ruleConfiguration' => $this->buildRateLimitConfiguration($state),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function buildRateLimitConfiguration(array $state): array
    {
        $path = trim((string) ($state['path_pattern'] ?? ''));

        return [
            'actionType' => $this->actionToCode((string) ($state['action'] ?? 'block')),
            'variableTypes' => [
                'REQUEST_URI' => 'REQUEST_URI',
            ],
            'operatorType' => 0,
            'severityType' => 0,
            'transformationTypes' => [1],
            'value' => $path !== '' ? $path : '*',
            'requestCount' => max(1, (int) ($state['requests'] ?? 100)),
            'counterKeyType' => 0,
            'timeframe' => $this->normalizeRateLimitTimeframe((int) ($state['window_seconds'] ?? 10)),
            'blockTime' => 30,
            'chainedRuleConditions' => [],
        ];
    }

    protected function sanitizeRateLimitText(string $value, string $fallback = 'RateLimit'): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? '';
        $sanitized = trim($sanitized);

        if ($sanitized === '') {
            return $fallback;
        }

        return Str::limit($sanitized, 64, '');
    }

    protected function normalizeRateLimitTimeframe(int $seconds): int
    {
        return in_array($seconds, [1, 10, 60, 300], true) ? $seconds : 10;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function buildShieldZonePatchPayload(
        int $shieldZoneId,
        array $current,
        array $overrides = [],
        int $challengeWindowMinutes = 30,
    ): array {
        return [
            'shieldZoneId' => $shieldZoneId,
            'shieldZone' => array_merge([
                'shieldZoneId' => $shieldZoneId,
                'learningMode' => (bool) (Arr::get($current, 'learningMode') ?? true),
                'learningModeUntil' => Arr::get($current, 'learningModeUntil'),
                'premiumPlan' => (bool) (Arr::get($current, 'premiumPlan') ?? Arr::get($current, 'PremiumPlan') ?? false),
                'planType' => (int) (Arr::get($current, 'planType') ?? Arr::get($current, 'PlanType') ?? 0),
                'wafEnabled' => (bool) (Arr::get($current, 'wafEnabled') ?? Arr::get($current, 'WafEnabled') ?? true),
                'wafExecutionMode' => (int) (Arr::get($current, 'wafExecutionMode') ?? Arr::get($current, 'WafExecutionMode') ?? 1),
                'wafDisabledRules' => array_values((array) Arr::get($current, 'wafDisabledRules', [])),
                'wafLogOnlyRules' => array_values((array) Arr::get($current, 'wafLogOnlyRules', [])),
                'wafRequestHeaderLoggingEnabled' => (bool) (Arr::get($current, 'wafRequestHeaderLoggingEnabled') ?? true),
                'wafRequestIgnoredHeaders' => array_values((array) Arr::get($current, 'wafRequestIgnoredHeaders', [])),
                'wafRealtimeThreatIntelligenceEnabled' => (bool) (Arr::get($current, 'wafRealtimeThreatIntelligenceEnabled') ?? false),
                'wafProfileId' => (int) (Arr::get($current, 'wafProfileId') ?? Arr::get($current, 'WafProfileId') ?? 1),
                'wafEngineConfig' => array_values((array) Arr::get($current, 'wafEngineConfig', [])),
                'wafRequestBodyLimitAction' => (int) (Arr::get($current, 'wafRequestBodyLimitAction') ?? 1),
                'wafResponseBodyLimitAction' => (int) (Arr::get($current, 'wafResponseBodyLimitAction') ?? 2),
                'dDoSShieldSensitivity' => (int) (Arr::get($current, 'dDoSShieldSensitivity') ?? 0),
                'dDoSExecutionMode' => (int) (Arr::get($current, 'dDoSExecutionMode') ?? 0),
                'dDoSChallengeWindow' => (int) (Arr::get($current, 'dDoSChallengeWindow') ?? ($challengeWindowMinutes * 60)),
                'blockVpn' => Arr::get($current, 'blockVpn'),
                'blockTor' => Arr::get($current, 'blockTor'),
                'blockDatacentre' => Arr::get($current, 'blockDatacentre'),
                'whitelabelResponsePages' => (bool) (Arr::get($current, 'whitelabelResponsePages') ?? false),
            ], $overrides),
        ];
    }

    protected function responseError(Response $response, string $fallback): string
    {
        $json = $response->json();
        $message = (string) (
            Arr::get($json, 'error.message')
            ?? Arr::get($json, 'errorResponse.message')
            ?? Arr::get($json, 'errors.0')
            ?? Arr::get($json, 'Message')
            ?? Arr::get($json, 'message')
            ?? ''
        );

        return $message !== '' ? $message : $fallback;
    }
}
