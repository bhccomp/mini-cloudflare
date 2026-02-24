<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkSiteReadyForCutoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $site = Site::query()->findOrFail($this->siteId);

        if ($site->status === Site::STATUS_FAILED) {
            return;
        }

        $isReady = filled($site->acm_certificate_arn)
            && filled($site->cloudfront_distribution_id)
            && filled($site->cloudfront_domain_name)
            && filled($site->waf_web_acl_arn);

        if (! $isReady) {
            $message = 'Deployment did not complete all edge prerequisites.';
            $site->update([
                'status' => Site::STATUS_FAILED,
                'last_error' => $message,
            ]);

            $this->audit($site, 'edge.deploy', 'failed', $message, []);

            return;
        }

        $site->update([
            'status' => Site::STATUS_READY_FOR_CUTOVER,
            'last_error' => null,
        ]);

        $this->audit($site, 'edge.deploy', 'success', 'Edge deployment complete; ready for DNS cutover.', []);
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
