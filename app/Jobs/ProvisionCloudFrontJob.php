<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionCloudFrontJob implements ShouldQueue
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
        $site->update(['provisioning_status' => 'provisioning_cloudfront']);

        try {
            $result = $aws->provisionCloudFront($site);

            if (($result['distribution_id'] ?? null) && ! $site->cloudfront_distribution_id) {
                $site->update([
                    'cloudfront_distribution_id' => $result['distribution_id'],
                    'cloudfront_domain_name' => $result['distribution_domain'] ?? null,
                    'provisioning_status' => 'cloudfront_ready',
                    'last_provision_error' => null,
                    'last_provisioned_at' => now(),
                ]);
            }

            AuditLog::create([
                'organization_id' => $site->organization_id,
                'site_id' => $site->id,
                'user_id' => $this->triggeredByUserId,
                'action' => 'cloudfront.provision',
                'status' => 'success',
                'message' => $result['message'] ?? 'CloudFront provisioning completed.',
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
                'action' => 'cloudfront.provision',
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
