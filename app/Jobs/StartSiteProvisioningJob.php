<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartSiteProvisioningJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $siteId,
        public ?int $actorId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(AwsEdgeService $aws): void
    {
        $site = Site::query()->findOrFail($this->siteId);

        if ($site->status === 'active' && $site->cloudfront_distribution_id && $site->waf_web_acl_arn) {
            $this->audit($site, 'site.provision.start', 'info', 'Site already active.', []);

            return;
        }

        $site->update([
            'status' => 'provisioning',
            'last_error' => null,
        ]);

        try {
            $result = $aws->requestAcmCertificate($site);

            $site->update([
                'acm_certificate_arn' => $result['certificate_arn'] ?? $site->acm_certificate_arn,
                'required_dns_records' => $result['required_dns_records'] ?? $site->required_dns_records,
                'status' => 'pending_dns',
                'last_error' => null,
            ]);

            $this->audit(
                $site,
                'site.provision.start',
                'success',
                $result['message'] ?? 'Certificate requested; waiting DNS validation.',
                $result,
            );
        } catch (\Throwable $e) {
            $site->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            $this->audit($site, 'site.provision.start', 'failed', $e->getMessage(), []);

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
