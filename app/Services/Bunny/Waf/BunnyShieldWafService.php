<?php

namespace App\Services\Bunny\Waf;

use App\Models\Site;
use App\Services\Bunny\BunnyApiService;
use App\Services\Bunny\BunnyShieldAccessListService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BunnyShieldWafService
{
    public function __construct(
        protected BunnyApiService $api,
        protected BunnyShieldAccessListService $accessLists,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function botDetectionSettings(Site $site): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $response = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}/bot-detection");

        if (! $response->successful()) {
            $saved = (array) data_get($site->provider_meta, 'bot_detection', []);

            return [
                'shield_zone_id' => $shieldZoneId,
                'enabled' => (bool) ($saved['enabled'] ?? false),
                'request_integrity_sensitivity' => (int) ($saved['request_integrity_sensitivity'] ?? 1),
                'ip_reputation_sensitivity' => (int) ($saved['ip_reputation_sensitivity'] ?? 1),
                'browser_fingerprint_sensitivity' => (int) ($saved['browser_fingerprint_sensitivity'] ?? 1),
                'browser_fingerprint_aggression' => (int) ($saved['browser_fingerprint_aggression'] ?? 1),
                'complex_fingerprinting' => (bool) ($saved['complex_fingerprinting'] ?? false),
                'raw' => [],
            ];
        }

        $data = (array) (Arr::get($response->json(), 'data') ?? []);

        return [
            'shield_zone_id' => $shieldZoneId,
            'enabled' => ((int) Arr::get($data, 'executionMode', 0)) > 0,
            'request_integrity_sensitivity' => (int) Arr::get($data, 'requestIntegrity.sensitivity', 1),
            'ip_reputation_sensitivity' => (int) Arr::get($data, 'ipAddress.sensitivity', 1),
            'browser_fingerprint_sensitivity' => (int) Arr::get($data, 'browserFingerprint.sensitivity', 1),
            'browser_fingerprint_aggression' => (int) Arr::get($data, 'browserFingerprint.aggression', 1),
            'complex_fingerprinting' => (bool) Arr::get($data, 'browserFingerprint.complexEnabled', false),
            'raw' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function updateBotDetectionSettings(Site $site, array $state): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);

        $payload = [
            'requestIntegrity' => [
                'sensitivity' => $this->clampSensitivity((int) ($state['request_integrity_sensitivity'] ?? 1)),
            ],
            'ipAddress' => [
                'sensitivity' => $this->clampSensitivity((int) ($state['ip_reputation_sensitivity'] ?? 1)),
            ],
            'browserFingerprint' => [
                'sensitivity' => $this->clampSensitivity((int) ($state['browser_fingerprint_sensitivity'] ?? 1)),
                'aggression' => $this->clampAggression((int) ($state['browser_fingerprint_aggression'] ?? 1)),
                'complexEnabled' => (bool) ($state['complex_fingerprinting'] ?? false),
            ],
            'executionMode' => (bool) ($state['enabled'] ?? false) ? 1 : 0,
        ];

        $response = $this->api->client()->patch("/shield/shield-zone/{$shieldZoneId}/bot-detection", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to update bot detection settings.'));
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['bot_detection'] = [
            'enabled' => (bool) ($state['enabled'] ?? false),
            'request_integrity_sensitivity' => (int) $payload['requestIntegrity']['sensitivity'],
            'ip_reputation_sensitivity' => (int) $payload['ipAddress']['sensitivity'],
            'browser_fingerprint_sensitivity' => (int) $payload['browserFingerprint']['sensitivity'],
            'browser_fingerprint_aggression' => (int) $payload['browserFingerprint']['aggression'],
            'complex_fingerprinting' => (bool) $payload['browserFingerprint']['complexEnabled'],
            'updated_at' => now()->toIso8601String(),
        ];
        $site->forceFill(['provider_meta' => $meta])->save();

        return ['updated' => true, 'shield_zone_id' => $shieldZoneId];
    }

    /**
     * @return array<string, mixed>
     */
    public function botDetectionMetrics(Site $site): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $response = $this->api->client()->get("/shield/metrics/shield-zone/{$shieldZoneId}/bot-detection");

        if (! $response->successful()) {
            return [];
        }

        return (array) (Arr::get($response->json(), 'data') ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function overviewMetrics(Site $site): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $response = $this->api->client()->get("/shield/metrics/overview/{$shieldZoneId}");

        if (! $response->successful()) {
            return [];
        }

        return (array) (Arr::get($response->json(), 'data') ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eventLogs(Site $site, int $limit = 20): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $dates = [now()->format('m-d-Y'), now()->subDay()->format('m-d-Y')];

        foreach ($dates as $date) {
            foreach (['start', ''] as $token) {
                $path = "/shield/event-logs/{$shieldZoneId}/{$date}";
                if ($token !== '') {
                    $path .= "/{$token}";
                }

                $response = $this->api->client()->get($path);

                if (! $response->successful()) {
                    continue;
                }

                $logs = collect((array) (Arr::get($response->json(), 'logs') ?? Arr::get($response->json(), 'data.logs') ?? []))
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row): array => $this->normalizeEventLog($row))
                    ->take($limit)
                    ->values()
                    ->all();

                if ($logs !== []) {
                    return $logs;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeEventLog(array $row): array
    {
        $labels = (array) ($row['labels'] ?? []);
        $rawMessage = trim((string) ($row['log'] ?? ''));
        $decoded = $this->decodeLogPayload($rawMessage);

        $path = $this->firstFilled([
            data_get($decoded, 'request.path'),
            data_get($decoded, 'path'),
            data_get($decoded, 'uri'),
            data_get($decoded, 'requestUri'),
            data_get($labels, 'path'),
            data_get($labels, 'uri'),
            data_get($labels, 'requestUri'),
        ]);

        $rule = $this->firstFilled([
            data_get($decoded, 'ruleName'),
            data_get($decoded, 'rule'),
            data_get($labels, 'ruleName'),
            data_get($labels, 'ruleGroup'),
            data_get($labels, 'matchedRule'),
        ]);

        $outcome = $this->firstFilled([
            data_get($decoded, 'action'),
            data_get($decoded, 'outcome'),
            data_get($decoded, 'decision'),
            data_get($labels, 'action'),
            data_get($labels, 'outcome'),
            data_get($labels, 'severity'),
            data_get($labels, 'eventType'),
        ]) ?? 'Observed';

        $country = strtoupper((string) ($this->firstFilled([
            data_get($decoded, 'country'),
            data_get($decoded, 'countryCode'),
            data_get($labels, 'country'),
            data_get($labels, 'countryCode'),
        ]) ?? ''));

        $host = $this->firstFilled([
            data_get($decoded, 'host'),
            data_get($decoded, 'hostname'),
            data_get($decoded, 'domain'),
            data_get($labels, 'host'),
            data_get($labels, 'hostname'),
        ]);

        $headline = $this->firstFilled([
            data_get($decoded, 'message'),
            data_get($decoded, 'title'),
            $rule ? "{$rule} triggered" : null,
            data_get($labels, 'eventType') ? $this->humanizeToken((string) data_get($labels, 'eventType')).' event' : null,
            data_get($labels, 'severity') ? $this->humanizeToken((string) data_get($labels, 'severity')).' event' : null,
        ]) ?? 'Security event detected';

        $summaryParts = collect([
            $this->humanizeToken($outcome),
            $country !== '' ? $country : null,
            $host,
        ])->filter()->values();

        return [
            'id' => (string) ($row['logId'] ?? ''),
            'timestamp' => (int) ($row['timestamp'] ?? 0),
            'headline' => Str::limit($headline, 96, '...'),
            'summary' => $summaryParts->isNotEmpty()
                ? $summaryParts->implode(' · ')
                : 'Edge protection event',
            'path' => $path ?: 'Not provided',
            'outcome' => $this->humanizeToken($outcome),
            'country' => $country !== '' ? $country : '--',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function triggeredRules(Site $site): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $response = $this->api->client()->get("/shield/waf/rules/review-triggered/{$shieldZoneId}");

        if (! $response->successful()) {
            return [];
        }

        return $this->extractRows($response->json());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function customRules(Site $site): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $response = $this->api->client()->get("/shield/waf/custom-rules/{$shieldZoneId}");

        if (! $response->successful()) {
            return [];
        }

        $enums = $this->wafEnums();

        return collect($this->extractRows($response->json()))
            ->map(fn (array $row): array => $this->normalizeCustomRuleRow($row, $enums))
            ->all();
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function wafEnums(): array
    {
        $response = $this->api->client()->get('/shield/waf/enums');

        if (! $response->successful()) {
            return [
                'actions' => [],
                'operators' => [],
                'transformations' => [],
            ];
        }

        $payload = $response->json();
        $groups = [
            'actions' => $this->enumMap(Arr::get($payload, 'actions', Arr::get($payload, 'data.actions', []))),
            'operators' => $this->enumMap(Arr::get($payload, 'operators', Arr::get($payload, 'data.operators', []))),
            'transformations' => $this->enumMap(Arr::get($payload, 'transformations', Arr::get($payload, 'data.transformations', []))),
        ];

        if ($groups['actions'] !== [] || $groups['operators'] !== [] || $groups['transformations'] !== []) {
            return $groups;
        }

        $rows = collect($this->extractRows($payload));

        return [
            'actions' => $this->enumMap($rows->filter(fn (array $row) => str_contains(strtolower((string) ($row['group'] ?? $row['type'] ?? '')), 'action'))->all()),
            'operators' => $this->enumMap($rows->filter(fn (array $row) => str_contains(strtolower((string) ($row['group'] ?? $row['type'] ?? '')), 'operator'))->all()),
            'transformations' => $this->enumMap($rows->filter(fn (array $row) => str_contains(strtolower((string) ($row['group'] ?? $row['type'] ?? '')), 'transform'))->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createCustomRule(Site $site, array $input): array
    {
        [$payload, $enums] = $this->buildCustomRulePayload($site, $input);

        $response = $this->api->client()->post('/shield/waf/custom-rule', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to create custom WAF rule.'));
        }

        $row = (array) (Arr::get($response->json(), 'data') ?? $response->json());

        return $this->normalizeCustomRuleRow($row, $enums);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function updateCustomRule(Site $site, string $ruleId, array $input): array
    {
        [$payload, $enums] = $this->buildCustomRulePayload($site, $input, $ruleId);

        $response = $this->api->client()->patch("/shield/waf/custom-rule/{$ruleId}", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to update custom WAF rule.'));
        }

        $row = (array) (Arr::get($response->json(), 'data') ?? $response->json());

        if ($row === []) {
            $row = [
                'id' => $ruleId,
                'wafRuleId' => $ruleId,
                'ruleName' => (string) ($input['name'] ?? 'Advanced rule'),
                'ruleDescription' => (string) ($input['description'] ?? ''),
                'ruleConfiguration' => $payload['ruleConfiguration'] ?? [],
            ];
        }

        return $this->normalizeCustomRuleRow($row, $enums);
    }

    public function deleteCustomRule(string $ruleId): void
    {
        $response = $this->api->client()->delete("/shield/waf/custom-rule/{$ruleId}");

        if ($response->successful() || in_array($response->status(), [404, 410], true)) {
            return;
        }

        throw new \RuntimeException($this->responseError($response, 'Unable to delete custom WAF rule.'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, array<string, int>>}
     */
    protected function buildCustomRulePayload(Site $site, array $input, ?string $ruleId = null): array
    {
        $shieldZoneId = $this->accessLists->ensureShieldZone($site);
        $enums = $this->wafEnums();

        $actionType = $this->enumId($enums['actions'], (string) ($input['action'] ?? 'block'), 1);
        $operatorType = $this->enumId($enums['operators'], (string) ($input['operator'] ?? 'contains'), 0);
        $transformationType = $this->enumId($enums['transformations'], (string) ($input['transformation'] ?? 'lowercase'), 1);

        $variable = strtoupper(trim((string) ($input['variable'] ?? 'REQUEST_URI')));
        $value = trim((string) ($input['value'] ?? ''));

        if ($value === '') {
            throw new \RuntimeException('Custom rule value cannot be empty.');
        }

        $chainedConditions = collect((array) ($input['conditions'] ?? []))
            ->filter(fn (mixed $condition): bool => is_array($condition))
            ->map(function (array $condition) use ($enums): array {
                $conditionVariable = strtoupper(trim((string) ($condition['variable'] ?? 'REQUEST_URI')));
                $conditionValue = trim((string) ($condition['value'] ?? ''));

                if ($conditionValue === '') {
                    return [];
                }

                $conditionOperatorType = $this->enumId($enums['operators'], (string) ($condition['operator'] ?? 'contains'), 0);
                $conditionTransformationType = $this->enumId($enums['transformations'], (string) ($condition['transformation'] ?? 'lowercase'), 1);

                return [
                    'variableTypes' => [$conditionVariable => $conditionVariable],
                    'operatorType' => $conditionOperatorType,
                    'transformationTypes' => [$conditionTransformationType],
                    'value' => $conditionValue,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $payload = [
            'shieldZoneId' => $shieldZoneId,
            'ruleName' => $this->sanitizeRuleText((string) ($input['name'] ?? 'CustomWafRule')),
            'ruleDescription' => $this->sanitizeRuleText((string) ($input['description'] ?? 'Custom edge WAF rule')),
            'ruleConfiguration' => [
                'actionType' => $actionType,
                'variableTypes' => [$variable => $variable],
                'operatorType' => $operatorType,
                'severityType' => 0,
                'transformationTypes' => [$transformationType],
                'value' => $value,
                'chainedRuleConditions' => $chainedConditions,
            ],
        ];

        if ($ruleId !== null) {
            $payload['wafRuleId'] = $ruleId;
        }

        return [$payload, $enums];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, int>>  $enums
     * @return array<string, mixed>
     */
    protected function normalizeCustomRuleRow(array $row, array $enums): array
    {
        $config = (array) ($row['ruleConfiguration'] ?? []);
        $variableTypes = (array) ($config['variableTypes'] ?? []);
        $transformations = array_values((array) ($config['transformationTypes'] ?? []));
        $conditions = collect((array) ($config['chainedRuleConditions'] ?? []))
            ->filter(fn (mixed $condition): bool => is_array($condition))
            ->map(function (array $condition) use ($enums): array {
                $conditionVariableTypes = (array) ($condition['variableTypes'] ?? []);
                $conditionTransformations = array_values((array) ($condition['transformationTypes'] ?? []));

                return [
                    'variable' => (string) array_key_first($conditionVariableTypes),
                    'operator' => $this->enumNameById($enums['operators'], (int) ($condition['operatorType'] ?? 0), 'contains'),
                    'transformation' => $this->enumNameById($enums['transformations'], (int) ($conditionTransformations[0] ?? 0), 'lower'),
                    'value' => (string) ($condition['value'] ?? ''),
                ];
            })
            ->values()
            ->all();

        $row['actionLabel'] = $this->enumNameById($enums['actions'], (int) ($config['actionType'] ?? 0), 'block');
        $row['operatorLabel'] = $this->enumNameById($enums['operators'], (int) ($config['operatorType'] ?? 0), 'contains');
        $row['transformationLabel'] = $this->enumNameById($enums['transformations'], (int) ($transformations[0] ?? 0), 'lower');
        $row['variableLabel'] = (string) array_key_first($variableTypes);
        $row['conditions'] = $conditions;

        return $row;
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $rows
     * @return array<string, int>
     */
    protected function enumMap(array $rows): array
    {
        $list = array_is_list($rows) ? $rows : [];

        return collect($list)
            ->filter(fn ($row) => is_array($row))
            ->mapWithKeys(function (array $row): array {
                $name = strtolower((string) ($row['name'] ?? $row['Name'] ?? ''));
                $id = Arr::get($row, 'id', Arr::get($row, 'Id'));

                return ($name !== '' && is_numeric($id)) ? [$name => (int) $id] : [];
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $map
     */
    protected function enumId(array $map, string $needle, int $fallback): int
    {
        $needle = strtolower(trim($needle));

        if (array_key_exists($needle, $map)) {
            return $map[$needle];
        }

        foreach ($map as $name => $id) {
            if (str_contains($name, $needle)) {
                return $id;
            }
        }

        return $fallback;
    }

    /**
     * @param  array<string, int>  $map
     */
    protected function enumNameById(array $map, int $id, string $fallback): string
    {
        foreach ($map as $name => $value) {
            if ($value === $id) {
                return $name;
            }
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeLogPayload(string $raw): ?array
    {
        if ($raw === '' || ! Str::startsWith($raw, ['{', '['])) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function firstFilled(array $values): mixed
    {
        return collect($values)->first(function (mixed $value): bool {
            if (is_string($value)) {
                return trim($value) !== '';
            }

            return ! is_null($value) && $value !== [];
        });
    }

    protected function humanizeToken(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return 'Observed';
        }

        return Str::title(Str::of($normalized)->replace(['_', '-'], ' ')->value());
    }

    protected function clampSensitivity(int $value): int
    {
        return max(0, min(3, $value));
    }

    protected function clampAggression(int $value): int
    {
        return max(0, min(3, $value));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractRows(mixed $payload): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        if (! is_array($payload)) {
            return [];
        }

        foreach (['data', 'items', 'rules', 'logs'] as $key) {
            $rows = $payload[$key] ?? null;

            if (is_array($rows) && array_is_list($rows)) {
                return array_values(array_filter($rows, 'is_array'));
            }
        }

        return [];
    }

    protected function sanitizeRuleText(string $value): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', '', Str::headline($value)) ?? '';

        return $normalized !== '' ? Str::limit($normalized, 60, '') : 'FirePhageRule';
    }

    protected function responseError(Response $response, string $fallback): string
    {
        return (string) (
            Arr::get($response->json(), 'error.message')
            ?? Arr::get($response->json(), 'Message')
            ?? Arr::get($response->json(), 'message')
            ?? $fallback
        );
    }
}
