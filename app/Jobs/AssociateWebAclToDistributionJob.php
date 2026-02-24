<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssociateWebAclToDistributionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(AwsEdgeService $aws): void
    {
        $site = Site::query()->findOrFail($this->siteId);

        try {
            $result = $aws->associateWebAclToDistribution($site);
            $site->update(['status' => Site::STATUS_DEPLOYING, 'last_error' => null]);

            $this->audit($site, 'waf.associate', 'success', $result['message'] ?? 'WAF associated to distribution.', $result);
        } catch (\Throwable $e) {
            $site->update(['status' => Site::STATUS_FAILED, 'last_error' => $e->getMessage()]);
            $this->audit($site, 'waf.associate', 'failed', $e->getMessage(), []);
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
