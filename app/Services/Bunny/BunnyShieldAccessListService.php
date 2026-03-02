<?php

namespace App\Services\Bunny;

use App\Models\Site;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class BunnyShieldAccessListService
{
    public function __construct(protected BunnyApiService $api) {}

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
        $enums = $this->accessListEnums();

        $ruleType = strtolower((string) ($rule['rule_type'] ?? 'ip'));
        $action = strtolower((string) ($rule['action'] ?? 'block'));
        $target = (string) ($rule['target'] ?? '');

        $typeId = $this->resolveEnumId($enums, 'type', $ruleType);
        $actionId = $this->resolveEnumId($enums, 'action', $action);

        $payload = [
            'Value' => $target,
            'Type' => $typeId,
            'ActionType' => $actionId,
            'Enabled' => true,
            'Comment' => (string) ($rule['note'] ?? ''),
        ];

        $response = $this->api->client()
            ->post("/shield/shield-zone/{$shieldZoneId}/access-lists", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to create access rule.'));
        }

        $data = $response->json();
        $providerRuleId = $this->extractRuleId($data);

        if ($providerRuleId !== null && ! empty($rule['expires_at'])) {
            $this->updateRuleConfiguration(
                shieldZoneId: $shieldZoneId,
                providerRuleId: $providerRuleId,
                actionId: $actionId,
                expiresAt: (string) $rule['expires_at'],
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

        $response = $this->api->client()->get('/shield/shield-zones', [
            'page' => 1,
            'perPage' => 200,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response, 'Unable to load shield zones.'));
        }

        $rows = collect($this->extractRows($response->json()));

        $matched = $rows->first(function (array $row) use ($pullZoneId): bool {
            $linkedPullZoneId = (int) (Arr::get($row, 'PullZoneId') ?? Arr::get($row, 'pullZoneId') ?? 0);

            return $linkedPullZoneId === $pullZoneId;
        });

        if (! is_array($matched)) {
            throw new \RuntimeException('Shield zone is not available for this edge deployment yet.');
        }

        $shieldZoneId = (int) (Arr::get($matched, 'Id') ?? Arr::get($matched, 'id') ?? 0);
        if ($shieldZoneId <= 0) {
            throw new \RuntimeException('Shield zone identifier is missing.');
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
            'action:allow' => 0,
            'action:block' => 1,
            'action:challenge' => 2,
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
        string $expiresAt,
    ): void {
        $this->api->client()->put(
            "/shield/shield-zone/{$shieldZoneId}/access-lists/configurations/{$providerRuleId}",
            [
                'ActionType' => $actionId,
                'IsEnabled' => true,
                'ExpiresAt' => $expiresAt,
            ],
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

        return [];
    }

    protected function extractRuleId(mixed $payload): ?string
    {
        if (is_array($payload)) {
            $id = Arr::get($payload, 'Id') ?? Arr::get($payload, 'id');

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
            ?? Arr::get($response->json(), 'error')
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
}
