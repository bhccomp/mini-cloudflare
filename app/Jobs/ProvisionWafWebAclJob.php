<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionWafWebAclJob implements ShouldQueue
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
            $result = $aws->provisionWafWebAcl($site, strict: (bool) $site->under_attack);
            $site->update([
                'waf_web_acl_arn' => $result['web_acl_arn'] ?? $site->waf_web_acl_arn,
                'status' => 'provisioning',
                'last_error' => null,
            ]);

            $this->audit($site, 'waf.provision', 'success', $result['message'] ?? 'WAF provisioned.', $result);
        } catch (\Throwable $e) {
            $site->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            $this->audit($site, 'waf.provision', 'failed', $e->getMessage(), []);
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
