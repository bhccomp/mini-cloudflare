<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssociateWebAclToDistributionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(EdgeProviderManager $providers): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        $provider = $providers->forSite($site);

        try {
            $result = $provider->provision($site);
            $site->update([
                'provider' => $provider->key(),
                'provider_resource_id' => $result['provider_resource_id'] ?? $site->provider_resource_id,
                'provider_meta' => $result['provider_meta'] ?? $site->provider_meta,
                'waf_web_acl_arn' => $result['web_acl_arn'] ?? $site->waf_web_acl_arn,
                'status' => Site::STATUS_DEPLOYING,
                'last_error' => null,
            ]);

            $this->audit($site, 'waf.associate', 'success', $result['message'] ?? 'Edge protection association applied.', $result + ['provider' => $provider->key()]);
        } catch (\Throwable $e) {
            $site->update(['status' => Site::STATUS_FAILED, 'last_error' => $e->getMessage()]);
            $this->audit($site, 'waf.associate', 'failed', $e->getMessage(), ['provider' => $provider->key()]);
            throw $e;
        }
    }

    protected function audit(Site $site, string $action, string $status, string $message, array $meta): void
    {
        AuditLog::create([
            'actor_id' => $this->actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'meta' => $meta,
        ]);
    }
}
