<?php

namespace App\Services\Bunny;

use App\Models\Site;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class BunnyShieldAccessListService
{
    public function __construct(protected BunnyApiService $api) {}

    public function ensureShieldZone(Site $site): int
    {
        try {
            return $this->resolveShieldZoneId($site);
        } catch (\Throwable) {
            // Continue with creation flow when the shield zone is missing.
        }

        $pullZoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($pullZoneId <= 0) {
            throw new \RuntimeException('Edge zone is not provisioned for this site.');
        }

        $responses = [
            $this->api->client()->post('/shield/shield-zone', [
                'PullZoneId' => $pullZoneId,
                'Enabled' => true,
            ]),
            $this->api->client()->post('/shield/shield-zone', [
                'pullZoneId' => $pullZoneId,
                'enabled' => true,
            ]),
            $this->api->client()->post('/shield/shield-zone', [
                'PullZoneId' => $pullZoneId,
            ]),
        ];

        foreach ($responses as $response) {
            if (! $response->successful()) {
                continue;
            }

            $payload = $response->json();
            $shieldZoneId = (int) (Arr::get($payload, 'Id')
                ?? Arr::get($payload, 'id')
                ?? Arr::get($payload, 'shieldZoneId')
                ?? Arr::get($payload, 'data.shieldZoneId')
                ?? 0);
            if ($shieldZoneId <= 0) {
                $shieldZoneId = $this->findShieldZoneIdByPullZone($pullZoneId);
            }

            if ($shieldZoneId <= 0) {
                continue;
            }

            $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
            $meta['shield_zone_id'] = $shieldZoneId;
            $site->forceFill(['provider_meta' => $meta])->save();

            return $shieldZoneId;
        }

        $resolved = $this->findShieldZoneIdByPullZone($pullZoneId);
        if ($resolved > 0) {
            $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
            $meta['shield_zone_id'] = $resolved;
            $site->forceFill(['provider_meta' => $meta])->save();

            return $resolved;
        }

        throw new \RuntimeException('Unable to create or resolve shield zone for this site.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRules(Site $site): array
    {
        $shieldZoneId = $this->resolveShieldZoneId($site);

        $response = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}/access-lists");

        if (! $response->successful()) {
            return [];
        }

        return $this->extractRows($response->json());
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function createRule(Site $site, array $rule): array
    {
        $shieldZoneId = $this->resolveShieldZoneId($site);

        $ruleType = strtolower((string) ($rule['rule_type'] ?? 'ip'));
        $action = strtolower((string) ($rule['action'] ?? 'block'));
        $target = (string) ($rule['target'] ?? '');
        $content = trim((string) ($rule['content'] ?? $target));

        $typeId = $this->resolveTypeCode($ruleType);
        $actionId = $this->resolveActionCode($action);

        $payload = [
            'name' => (string) ($rule['name'] ?? $this->defaultRuleName($ruleType, $target)),
            'description' => (string) ($rule['note'] ?? ''),
            'type' => $typeId,
            'action' => $actionId,
            'isEnabled' => true,
            'content' => strtoupper($content),
        ];

        $response = $this->api->client()
            ->post("/shield/shield-zone/{$shieldZoneId}/access-lists", $payload);

        $responseJson = $response->json();
        if (! $response->successful() || $this->isOperationFailure($responseJson)) {
            if ($this->isLimitExceededError($responseJson)) {
                return $this->updateExistingCustomList(
                    site: $site,
                    shieldZoneId: $shieldZoneId,
                    payload: $payload,
                    actionId: $actionId,
                );
            }

            throw new \RuntimeException($this->responseError($response, 'Unable to create access rule.'));
        }

        $data = $responseJson;
        $providerRuleId = $this->extractRuleId($data);
        $configurationId = $this->extractConfigurationId($data);

        if ($providerRuleId !== null && $configurationId === null) {
            $configurationId = $this->findConfigurationIdByListId($site, $providerRuleId);
        }

        if ($configurationId !== null) {
            $this->updateRuleConfiguration(
                shieldZoneId: $shieldZoneId,
                providerRuleId: $configurationId,
                actionId: $actionId,
                expiresAt: ! empty($rule['expires_at']) ? (string) $rule['expires_at'] : null,
            );
        }

        return [
            'provider_rule_id' => $providerRuleId,
            'response' => $data,
        ];
    }

    public function deleteRule(Site $site, string $providerRuleId): void
    {
        $shieldZoneId = $this->resolveShieldZoneId($site);

        $response = $this->api->client()
            ->delete("/shield/shield-zone/{$shieldZoneId}/access-lists/{$providerRuleId}");

        if ($response->successful() || in_array($response->status(), [404, 410], true)) {
            return;
        }

        throw new \RuntimeException($this->responseError($response, 'Unable to delete access rule.'));
    }

    /**
     * @return array<string, string>
     */
    public function countries(): array
    {
        foreach (['/countries', '/country/list', '/country'] as $endpoint) {
            try {
                $response = $this->api->client()->get($endpoint);

                if (! $response->successful()) {
                    continue;
                }

                $mapped = $this->mapCountriesFromPayload($response->json());
                if ($mapped !== []) {
                    asort($mapped);

                    return $mapped;
                }
            } catch (\Throwable) {
                // Ignore API/runtime errors and use local fallback.
            }
        }

        return $this->fallbackCountries();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function continentCountries(): array
    {
        foreach (['/region/list', '/region'] as $endpoint) {
            $response = $this->api->client()->get($endpoint);

            if (! $response->successful()) {
                continue;
            }

            $rows = $this->extractRows($response->json());
            if ($rows === []) {
                continue;
            }

            /** @var array<string, array<int, string>> $map */
            $map = [];

            foreach ($rows as $row) {
                $continent = strtoupper((string) (Arr::get($row, 'ContinentCode') ?? Arr::get($row, 'continentCode') ?? ''));
                $country = strtoupper((string) (Arr::get($row, 'CountryCode') ?? Arr::get($row, 'countryCode') ?? ''));

                if ($continent === '' || $country === '' || strlen($continent) > 2 || strlen($country) !== 2) {
                    continue;
                }

                $map[$continent] ??= [];
                $map[$continent][] = $country;
            }

            if ($map !== []) {
                foreach ($map as $key => $codes) {
                    $map[$key] = array_values(array_unique($codes));
                }

                return $map;
            }
        }

        return [];
    }

    public function resolveShieldZoneId(Site $site): int
    {
        $existing = (int) data_get($site->provider_meta, 'shield_zone_id', 0);
        if ($existing > 0) {
            return $existing;
        }

        $pullZoneId = (int) ($site->provider_resource_id ?: data_get($site->provider_meta, 'zone_id', 0));
        if ($pullZoneId <= 0) {
            throw new \RuntimeException('Edge zone is not provisioned for this site.');
        }

        $shieldZoneId = $this->findShieldZoneIdByPullZone($pullZoneId);
        if ($shieldZoneId <= 0) {
            throw new \RuntimeException('Shield zone is not available for this edge deployment yet.');
        }

        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['shield_zone_id'] = $shieldZoneId;
        $site->forceFill(['provider_meta' => $meta])->save();

        return $shieldZoneId;
    }

    /**
     * @return array<string, Collection<int, array{name: string, id: int}>>
     */
    protected function accessListEnums(): array
    {
        $response = $this->api->client()->get('/shield/access-lists/enums');

        if (! $response->successful()) {
            return [
                'type' => collect(),
                'action' => collect(),
            ];
        }

        $payload = $response->json();
        $normalized = collect($this->extractRows($payload))
            ->map(function (array $row): array {
                return [
                    'name' => strtolower((string) (Arr::get($row, 'Name') ?? Arr::get($row, 'name') ?? '')),
                    'id' => (int) (Arr::get($row, 'Id') ?? Arr::get($row, 'id') ?? -1),
                    'group' => strtolower((string) (Arr::get($row, 'Group') ?? Arr::get($row, 'group') ?? Arr::get($row, 'Type') ?? Arr::get($row, 'type') ?? '')),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '' && $row['id'] >= 0);

        $type = $normalized
            ->filter(fn (array $row): bool => str_contains($row['group'], 'type'))
            ->map(fn (array $row): array => ['name' => $row['name'], 'id' => $row['id']])
            ->values();

        $action = $normalized
            ->filter(fn (array $row): bool => str_contains($row['group'], 'action'))
            ->map(fn (array $row): array => ['name' => $row['name'], 'id' => $row['id']])
            ->values();

        return compact('type', 'action');
    }

    protected function resolveTypeCode(string $ruleType): int
    {
        return match (strtolower(trim($ruleType))) {
            'ip' => 0,
            'cidr' => 1,
            'asn' => 2,
            'country' => 3,
            default => 0,
        };
    }

    protected function resolveActionCode(string $action): int
    {
        return match (strtolower(trim($action))) {
            // Bunny Shield Access List actions:
            // 0 = bypass, 1 = allow, 2 = block, 3 = challenge, 4 = log
            'allow' => 1,
            'challenge' => 3,
            default => 2,
        };
    }

    /**
     * @param  array<string, Collection<int, array{name: string, id: int}>>  $enums
     */
    protected function resolveEnumId(array $enums, string $group, string $label): int|string
    {
        $fallback = match ("{$group}:{$label}") {
            'type:ip' => 0,
            'type:cidr' => 1,
            'type:country' => 2,
            'type:continent' => 3,
            'action:allow' => 1,
            'action:block' => 2,
            'action:challenge' => 3,
            default => 0,
        };

        $needle = strtolower($label);
        $mapped = $enums[$group]
            ->first(fn (array $row): bool => str_contains($row['name'], $needle));

        return is_array($mapped) ? (int) $mapped['id'] : $fallback;
    }

    protected function updateRuleConfiguration(
        int $shieldZoneId,
        string $providerRuleId,
        int|string $actionId,
        ?string $expiresAt = null,
    ): void {
        $payload = [
            'action' => (int) $actionId,
            'isEnabled' => true,
        ];

        if (is_string($expiresAt) && $expiresAt !== '') {
            $payload['expiresAt'] = $expiresAt;
        }

        $this->api->client()->patch(
            "/shield/shield-zone/{$shieldZoneId}/access-lists/configurations/{$providerRuleId}",
            $payload,
        );
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

        foreach (['Items', 'items', 'records', 'Records', 'data', 'Data'] as $key) {
            $rows = $payload[$key] ?? null;

            if (is_array($rows) && array_is_list($rows)) {
                return array_values(array_filter($rows, 'is_array'));
            }
        }

        $managed = $payload['managedLists'] ?? null;
        $custom = $payload['customLists'] ?? null;
        if (is_array($managed) || is_array($custom)) {
            $rows = array_merge(
                is_array($managed) ? $managed : [],
                is_array($custom) ? $custom : [],
            );

            if ($rows !== []) {
                return array_values(array_filter($rows, 'is_array'));
            }
        }

        return [];
    }

    protected function extractRuleId(mixed $payload): ?string
    {
        if (is_array($payload)) {
            $id = Arr::get($payload, 'Id')
                ?? Arr::get($payload, 'id')
                ?? Arr::get($payload, 'data.id')
                ?? Arr::get($payload, 'listId')
                ?? Arr::get($payload, 'data.listId');

            if (is_scalar($id)) {
                return (string) $id;
            }
        }

        return null;
    }

    protected function extractConfigurationId(mixed $payload): ?string
    {
        if (is_array($payload)) {
            $id = Arr::get($payload, 'configurationId')
                ?? Arr::get($payload, 'ConfigurationId')
                ?? Arr::get($payload, 'data.configurationId')
                ?? Arr::get($payload, 'data.ConfigurationId');

            if (is_scalar($id)) {
                return (string) $id;
            }
        }

        return null;
    }

    protected function responseError(Response $response, string $fallback): string
    {
        $message = (string) (Arr::get($response->json(), 'Message')
            ?? Arr::get($response->json(), 'message')
            ?? Arr::get($response->json(), 'error.message')
            ?? Arr::get($response->json(), 'error')
            ?? Arr::get($response->json(), 'errors.$.0')
            ?? '');

        return $message !== '' ? $message : $fallback;
    }

    /**
     * @return array<string, string>
     */
    protected function mapCountriesFromPayload(mixed $payload): array
    {
        $rows = $this->extractRows($payload);

        $mapped = collect($rows)
            ->mapWithKeys(function (array $row): array {
                $code = strtoupper((string) (Arr::get($row, 'Code') ?? Arr::get($row, 'code') ?? Arr::get($row, 'IsoCode') ?? Arr::get($row, 'isoCode') ?? ''));
                $name = (string) (Arr::get($row, 'Name') ?? Arr::get($row, 'name') ?? $code);

                return ($code !== '' && strlen($code) === 2) ? [$code => $name] : [];
            })
            ->all();

        if ($mapped !== []) {
            return $mapped;
        }

        if (! is_array($payload)) {
            return [];
        }

        // Some APIs return { "US": "United States", ... } object maps.
        return collect($payload)
            ->mapWithKeys(function (mixed $value, mixed $key): array {
                $code = strtoupper(trim((string) $key));
                $name = trim((string) $value);

                return preg_match('/^[A-Z]{2}$/', $code) === 1 && $name !== '' ? [$code => $name] : [];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function fallbackCountries(): array
    {
        if (! class_exists(\ResourceBundle::class)) {
            return [];
        }

        $bundle = \ResourceBundle::create('en', 'ICUDATA-region');
        if (! $bundle instanceof \ResourceBundle) {
            return [];
        }

        $mapped = [];
        foreach ($bundle as $code => $name) {
            $countryCode = strtoupper((string) $code);
            $countryName = trim((string) $name);

            if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1 || $countryName === '') {
                continue;
            }

            $mapped[$countryCode] = $countryName;
        }

        asort($mapped);

        return $mapped;
    }

    protected function findShieldZoneIdByPullZone(int $pullZoneId): int
    {
        if ($pullZoneId <= 0) {
            return 0;
        }

        $response = $this->api->client()->get('/shield/shield-zones', [
            'page' => 1,
            'perPage' => 200,
        ]);

        if (! $response->successful()) {
            return 0;
        }

        $rows = collect($this->extractRows($response->json()));
        $matched = $rows->first(function (array $row) use ($pullZoneId): bool {
            $linkedPullZoneId = (int) (Arr::get($row, 'PullZoneId')
                ?? Arr::get($row, 'pullZoneId')
                ?? Arr::get($row, 'pull_zone_id')
                ?? 0);

            return $linkedPullZoneId === $pullZoneId;
        });

        if (! is_array($matched)) {
            return 0;
        }

        return (int) (Arr::get($matched, 'Id')
            ?? Arr::get($matched, 'id')
            ?? Arr::get($matched, 'shieldZoneId')
            ?? Arr::get($matched, 'shield_zone_id')
            ?? 0);
    }

    protected function defaultRuleName(string $ruleType, string $target): string
    {
        $label = strtoupper(trim($target));
        $type = strtoupper(trim($ruleType));

        return trim("FP {$type} {$label}");
    }

    protected function findConfigurationIdByListId(Site $site, string $listId): ?string
    {
        $rows = $this->listRules($site);

        foreach ($rows as $row) {
            $currentListId = (string) (Arr::get($row, 'listId') ?? Arr::get($row, 'id') ?? Arr::get($row, 'Id') ?? '');
            if ($currentListId !== (string) $listId) {
                continue;
            }

            $configurationId = Arr::get($row, 'configurationId') ?? Arr::get($row, 'ConfigurationId');
            if (is_scalar($configurationId)) {
                return (string) $configurationId;
            }
        }

        return null;
    }

    protected function isOperationFailure(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $success = Arr::get($payload, 'error.success');

        return is_bool($success) && $success === false;
    }

    protected function isLimitExceededError(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $errorKey = strtolower((string) Arr::get($payload, 'error.errorKey', ''));

        return str_contains($errorKey, 'limit_reached.access_list');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function updateExistingCustomList(
        Site $site,
        int $shieldZoneId,
        array $payload,
        int|string $actionId,
    ): array {
        $current = $this->api->client()->get("/shield/shield-zone/{$shieldZoneId}/access-lists");
        if (! $current->successful()) {
            throw new \RuntimeException('Access list limit reached and existing list could not be loaded.');
        }

        $customLists = (array) Arr::get($current->json(), 'customLists', []);
        $candidate = collect($customLists)
            ->first(fn (array $row): bool => (int) (Arr::get($row, 'listId') ?? 0) > 0);

        if (! is_array($candidate)) {
            throw new \RuntimeException('Access list limit reached. No editable custom list was found.');
        }

        $listId = (string) (Arr::get($candidate, 'listId') ?? Arr::get($candidate, 'id') ?? '');
        $configurationId = (string) (Arr::get($candidate, 'configurationId') ?? '');
        if ($listId === '') {
            throw new \RuntimeException('Access list limit reached. Existing list identifier is missing.');
        }

        $patch = $this->api->client()->patch(
            "/shield/shield-zone/{$shieldZoneId}/access-lists/{$listId}",
            [
                'name' => (string) ($payload['name'] ?? 'Firewall rule'),
                'description' => (string) ($payload['description'] ?? ''),
                'type' => (int) ($payload['type'] ?? 3),
                'content' => (string) ($payload['content'] ?? ''),
            ],
        );

        if (! $patch->successful()) {
            throw new \RuntimeException($this->responseError($patch, 'Unable to update existing custom access list.'));
        }

        if ($configurationId !== '') {
            $this->updateRuleConfiguration(
                shieldZoneId: $shieldZoneId,
                providerRuleId: $configurationId,
                actionId: $actionId,
            );
        }

        return [
            'provider_rule_id' => $listId,
            'response' => $patch->json(),
        ];
    }
}
