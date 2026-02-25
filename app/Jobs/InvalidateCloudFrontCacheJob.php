<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InvalidateCloudFrontCacheJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public array $paths = ['/*'], public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(EdgeProviderManager $providers): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        $provider = $providers->forSite($site);
        $result = $provider->purgeCache($site, $this->paths);

        AuditLog::create([
            'actor_id' => $this->actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => 'cloudfront.invalidate',
            'status' => ($result['changed'] ?? false) ? 'success' : 'info',
            'message' => $result['message'] ?? 'Cache purge requested.',
            'meta' => $result + ['provider' => $provider->key()],
        ]);
    }
}
