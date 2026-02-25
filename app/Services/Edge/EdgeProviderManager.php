<?php

namespace App\Services\Edge;

use App\Models\Site;
use App\Services\Edge\Providers\AwsCdnProvider;
use App\Services\Edge\Providers\BunnyCdnProvider;
use InvalidArgumentException;

class EdgeProviderManager
{
    /**
     * @var array<string, EdgeProviderInterface>
     */
    protected array $providers;

    public function __construct(AwsCdnProvider $aws, BunnyCdnProvider $bunny)
    {
        $this->providers = [
            $aws->key() => $aws,
            $bunny->key() => $bunny,
        ];
    }

    public function defaultKey(): string
    {
        $key = strtolower((string) config('edge.default_provider', Site::PROVIDER_AWS));

        return array_key_exists($key, $this->providers)
            ? $key
            : Site::PROVIDER_AWS;
    }

    public function forSite(Site $site): EdgeProviderInterface
    {
        $key = strtolower((string) ($site->provider ?: $this->defaultKey()));

        return $this->for($key);
    }

    public function for(string $key): EdgeProviderInterface
    {
        $normalized = strtolower(trim($key));

        if (! array_key_exists($normalized, $this->providers)) {
            throw new InvalidArgumentException("Unsupported edge provider [{$normalized}].");
        }

        return $this->providers[$normalized];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
