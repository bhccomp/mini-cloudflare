<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionWafJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $siteId,
        public ?int $triggeredByUserId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(AwsEdgeService $aws): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        $site->update(['provisioning_status' => 'provisioning_waf']);

        try {
            $result = $aws->provisionWaf($site);

            $site->update([
                'waf_web_acl_arn' => $site->waf_web_acl_arn ?: ($result['web_acl_arn'] ?? null),
                'provisioning_status' => $site->waf_web_acl_arn || ($result['web_acl_arn'] ?? null) ? 'ready' : 'provisioning_waf',
                'last_provision_error' => null,
                'last_provisioned_at' => now(),
            ]);

            AuditLog::create([
                'organization_id' => $site->organization_id,
                'site_id' => $site->id,
                'user_id' => $this->triggeredByUserId,
                'action' => 'waf.provision',
                'status' => 'success',
                'message' => $result['message'] ?? 'WAF provisioning completed.',
                'context' => $result,
            ]);
        } catch (\Throwable $e) {
            $site->update([
                'provisioning_status' => 'failed',
                'last_provision_error' => $e->getMessage(),
            ]);

            AuditLog::create([
                'organization_id' => $site->organization_id,
                'site_id' => $site->id,
                'user_id' => $this->triggeredByUserId,
                'action' => 'waf.provision',
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
