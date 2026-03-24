<?php

namespace App\Services\Bunny;

use App\Models\Site;
use App\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class BunnyGlobalDefaultsService
{
    private const SETTING_KEY = 'bunny';

    private const DESCRIPTION_PREFIX = '[FP_DEFAULT:';

    /**
     * @return array{
     *   cache_exclusions: array<int, array{path_pattern:string,reason:string,enabled:bool}>,
     *   security_rate_limits: array<int, array{slug:string,name:string,description:string,action:string,window_seconds:int,requests:int,path_pattern:string,enabled:bool}>
     * }
     */
    public function defaults(): array
    {
        $value = $this->settingValue();

        return [
            'cache_exclusions' => $this->normalizeCacheExclusions((array) ($value['global_cache_exclusions'] ?? [])),
            'security_rate_limits' => $this->normalizeSecurityRateLimits((array) ($value['global_security_rate_limits'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(array $state): void
    {
        $setting = SystemSetting::query()->firstOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => [], 'is_encrypted' => false, 'description' => 'Bunny operational settings']
        );

        $value = is_array($setting->value) ? $setting->value : [];
        $value['global_cache_exclusions'] = $this->normalizeCacheExclusions((array) ($state['cache_exclusions'] ?? []));
        $value['global_security_rate_limits'] = $this->normalizeSecurityRateLimits((array) ($state['security_rate_limits'] ?? []));

        $setting->forceFill([
            'value' => $value,
        ])->save();
    }

    public function syncSecurityDefaults(Site $site): int
    {
        if ($site->provider !== Site::PROVIDER_BUNNY) {
            return 0;
        }

        $service = app(BunnyShieldSecurityService::class);
        $defaults = collect($this->defaults()['security_rate_limits'])
            ->where('enabled', true)
            ->values();

        $existing = collect($service->listRateLimits($site));
        $managed = $existing
            ->filter(fn (array $row): bool => $this->extractDefaultSlug($row) !== null)
            ->values();

        foreach ($managed as $row) {
            $id = (string) (data_get($row, 'id') ?? data_get($row, 'Id') ?? '');

            if ($id !== '') {
                $service->deleteRateLimit($site, $id);
            }
        }

        $created = 0;

        foreach ($defaults as $rule) {
            $description = trim($this->descriptionTag((string) $rule['slug']).' '.(string) $rule['description']);

            $service->createRateLimit($site, [
                'action' => (string) $rule['action'],
                'window_seconds' => (int) $rule['window_seconds'],
                'requests' => (int) $rule['requests'],
                'name' => (string) $rule['name'],
                'description' => $description,
                'path_pattern' => (string) $rule['path_pattern'],
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function filterVisibleRateLimits(array $rows): array
    {
        return collect($rows)
            ->filter(fn (array $row): bool => $this->extractDefaultSlug($row) === null)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{slug:string,name:string,description:string,action:string,window_seconds:int,requests:int,path_pattern:string,enabled:bool}>
     */
    public function activeSecurityRateLimits(): array
    {
        return collect($this->defaults()['security_rate_limits'])
            ->where('enabled', true)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Site>
     */
    public function bunnySites(): Collection
    {
        return Site::query()
            ->where('provider', Site::PROVIDER_BUNNY)
            ->whereNotNull('provider_resource_id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function settingValue(): array
    {
        $value = SystemSetting::query()->where('key', self::SETTING_KEY)->value('value');

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array{slug:string,name:string,description:string,action:string,window_seconds:int,requests:int,path_pattern:string,enabled:bool}>
     */
    private function normalizeSecurityRateLimits(array $rules): array
    {
        $fallback = $this->defaultSecurityRateLimits();
        $normalized = collect($rules)
            ->map(function (array $row, int $index) use ($fallback): array {
                $fallbackRow = $fallback[$index] ?? [];
                $slug = $this->slugify((string) ($row['slug'] ?? $fallbackRow['slug'] ?? 'default-rule'));

                return [
                    'slug' => $slug,
                    'name' => trim((string) ($row['name'] ?? $fallbackRow['name'] ?? 'FirePhage Default Rule')),
                    'description' => trim((string) ($row['description'] ?? $fallbackRow['description'] ?? '')),
                    'action' => $this->normalizeAction((string) ($row['action'] ?? $fallbackRow['action'] ?? 'challenge')),
                    'window_seconds' => max(1, (int) ($row['window_seconds'] ?? $fallbackRow['window_seconds'] ?? 60)),
                    'requests' => max(1, (int) ($row['requests'] ?? $fallbackRow['requests'] ?? 20)),
                    'path_pattern' => trim((string) ($row['path_pattern'] ?? $fallbackRow['path_pattern'] ?? '')),
                    'enabled' => (bool) ($row['enabled'] ?? $fallbackRow['enabled'] ?? true),
                ];
            })
            ->filter(fn (array $row): bool => $row['slug'] !== '' && $row['name'] !== '')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $fallback;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array{path_pattern:string,reason:string,enabled:bool}>
     */
    private function normalizeCacheExclusions(array $rules): array
    {
        $fallback = $this->defaultCacheExclusions();
        $normalized = collect($rules)
            ->map(function (array $row, int $index) use ($fallback): array {
                $fallbackRow = $fallback[$index] ?? [];

                return [
                    'path_pattern' => trim((string) ($row['path_pattern'] ?? $fallbackRow['path_pattern'] ?? '')),
                    'reason' => trim((string) ($row['reason'] ?? $fallbackRow['reason'] ?? '')),
                    'enabled' => (bool) ($row['enabled'] ?? $fallbackRow['enabled'] ?? true),
                ];
            })
            ->filter(fn (array $row): bool => $row['path_pattern'] !== '')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $fallback;
    }

    /**
     * @return array<int, array{slug:string,name:string,description:string,action:string,window_seconds:int,requests:int,path_pattern:string,enabled:bool}>
     */
    private function defaultSecurityRateLimits(): array
    {
        return [
            [
                'slug' => 'wordpress-login',
                'name' => 'FirePhage Default: WordPress Login',
                'description' => 'Protects the WordPress login endpoint from burst abuse.',
                'action' => 'challenge',
                'window_seconds' => 60,
                'requests' => 20,
                'path_pattern' => '/wp-login.php*',
                'enabled' => true,
            ],
            [
                'slug' => 'wordpress-xmlrpc',
                'name' => 'FirePhage Default: XML-RPC',
                'description' => 'Blocks repeated XML-RPC abuse patterns by default.',
                'action' => 'block',
                'window_seconds' => 60,
                'requests' => 15,
                'path_pattern' => '/xmlrpc.php*',
                'enabled' => true,
            ],
            [
                'slug' => 'wordpress-admin',
                'name' => 'FirePhage Default: Admin Burst',
                'description' => 'Challenges unusual bursts against WordPress admin paths.',
                'action' => 'challenge',
                'window_seconds' => 60,
                'requests' => 120,
                'path_pattern' => '/wp-admin/*',
                'enabled' => true,
            ],
        ];
    }

    /**
     * @return array<int, array{path_pattern:string,reason:string,enabled:bool}>
     */
    private function defaultCacheExclusions(): array
    {
        return [
            [
                'path_pattern' => '/wp-admin/*',
                'reason' => 'Keep WordPress admin traffic uncached and unoptimized.',
                'enabled' => true,
            ],
            [
                'path_pattern' => '/wp-login.php*',
                'reason' => 'Keep login requests uncached and unoptimized.',
                'enabled' => true,
            ],
            [
                'path_pattern' => '/wp-json/*',
                'reason' => 'Reserved for API-safe defaults when edge cache rules are enabled.',
                'enabled' => false,
            ],
        ];
    }

    private function descriptionTag(string $slug): string
    {
        return self::DESCRIPTION_PREFIX.$slug.']';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractDefaultSlug(array $row): ?string
    {
        $description = (string) (data_get($row, 'description') ?? data_get($row, 'Description') ?? '');

        if (preg_match('/^\[FP_DEFAULT:([a-z0-9\-]+)\]/i', trim($description), $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    private function normalizeAction(string $action): string
    {
        $normalized = strtolower(trim($action));

        return in_array($normalized, ['allow', 'block', 'challenge'], true) ? $normalized : 'challenge';
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';

        return trim($slug, '-');
    }
}
