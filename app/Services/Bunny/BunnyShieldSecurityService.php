<?php

namespace App\Services\Bunny;

use App\Models\Site;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

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
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $wafSensitivity = $this->normalizeSensitivity((string) ($state['waf_sensitivity'] ?? 'medium'));
        $ddosSensitivity = $this->normalizeSensitivity((string) ($state['ddos_sensitivity'] ?? 'medium'));
        $botSensitivity = $this->normalizeSensitivity((string) ($state['bot_sensitivity'] ?? 'medium'));
        $challengeWindow = max(5, (int) ($state['challenge_window_minutes'] ?? 30));
        $current = $this->normalizeEnvelope($this->api->client()->get("/shield/shield-zone/{$shieldZoneId}")->json());

        $payload = $this->buildShieldZonePatchPayload($shieldZoneId, $current, [
            'wafEnabled' => true,
            'wafExecutionMode' => $this->sensitivityToCode($wafSensitivity),
            'wafRealtimeThreatIntelligenceEnabled' => in_array($ddosSensitivity, ['high', 'extreme'], true),
            'wafProfileId' => $this->sensitivityToCode($wafSensitivity),
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

        if ($premiumPlan && $currentPlanType === $planType) {
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

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['shield_plan'] = 'advanced';
        $meta['shield_premium_plan'] = true;
        $meta['shield_plan_type'] = $planType;
        $meta['shield_plan_upgraded_at'] = now()->toIso8601String();
        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'shield_zone_id' => $shieldZoneId,
            'changed' => true,
            'message' => 'Bunny Shield advanced plan enabled.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setTroubleshootingMode(Site $site, bool $enabled, ?bool $restoreWafEnabled = null): array
    {
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

        if (! $premiumPlan) {
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
     * @return array<int, array<string, mixed>>
     */
    public function listRateLimits(Site $site): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);

        $responses = [
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
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function createRateLimit(Site $site, array $state): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $requests = max(1, (int) ($state['requests'] ?? 100));
        $window = max(1, (int) ($state['window_seconds'] ?? 10));
        $action = strtolower((string) ($state['action'] ?? 'block'));
        $path = trim((string) ($state['path_pattern'] ?? ''));

        $ruleConfig = [
            'windowSeconds' => $window,
            'requestLimit' => $requests,
            'pathPattern' => $path !== '' ? $path : null,
        ];

        $payload = [
            'ShieldZoneId' => $shieldZoneId,
            'Name' => (string) ($state['name'] ?? 'Rate limit'),
            'Description' => (string) ($state['description'] ?? ''),
            'Enabled' => true,
            'ActionType' => $this->actionToCode($action),
            'RuleConfiguration' => $ruleConfig,
            'RuleJson' => json_encode($ruleConfig, JSON_UNESCAPED_SLASHES),
        ];

        $responses = [
            $this->api->client()->post('/shield/rate-limit', $payload),
            $this->api->client()->post('/shield/rate-limits', $payload),
            $this->api->client()->post('/shield/rate-limit', [
                'shieldZoneId' => $shieldZoneId,
                'name' => (string) ($state['name'] ?? 'Rate limit'),
                'description' => (string) ($state['description'] ?? ''),
                'enabled' => true,
                'actionType' => $this->actionToCode($action),
                'ruleConfiguration' => $ruleConfig,
                'ruleJson' => json_encode($ruleConfig, JSON_UNESCAPED_SLASHES),
            ]),
        ];

        foreach ($responses as $response) {
            if (! $response->successful()) {
                continue;
            }

            $normalized = $this->normalizeEnvelope($response->json());

            return [
                'id' => (string) (Arr::get($normalized, 'id') ?? Arr::get($normalized, 'Id') ?? ''),
                'response' => $normalized,
            ];
        }

        throw new \RuntimeException('Unable to create rate limit rule with current payload.');
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
            'allow' => 0,
            'challenge' => 2,
            default => 1,
        };
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
            ?? Arr::get($json, 'Message')
            ?? Arr::get($json, 'message')
            ?? ''
        );

        return $message !== '' ? $message : $fallback;
    }
}
