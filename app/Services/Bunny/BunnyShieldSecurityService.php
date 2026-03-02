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

        $payload = [
            'WafEnabled' => true,
            'WafExecutionMode' => $this->sensitivityToCode($wafSensitivity),
            'WafProfileId' => $this->sensitivityToCode($wafSensitivity),
            'WafRealtimeThreatIntelligenceEnabled' => in_array($ddosSensitivity, ['high', 'extreme'], true),
        ];

        $response = $this->api->client()->put("/shield/shield-zone/{$shieldZoneId}", $payload);

        if (! $response->successful()) {
            $response = $this->api->client()->put("/shield/shield-zone/{$shieldZoneId}", [
                'wafEnabled' => true,
                'wafExecutionMode' => $this->sensitivityToCode($wafSensitivity),
                'wafProfileId' => $this->sensitivityToCode($wafSensitivity),
                'wafRealtimeThreatIntelligenceEnabled' => in_array($ddosSensitivity, ['high', 'extreme'], true),
            ]);
        }

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
            'updated_at' => now()->toIso8601String(),
        ];
        $site->forceFill(['provider_meta' => $meta])->save();

        return [
            'shield_zone_id' => $shieldZoneId,
            'updated' => true,
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
