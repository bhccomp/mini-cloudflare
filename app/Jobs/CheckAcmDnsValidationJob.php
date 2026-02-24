<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;

class CheckAcmDnsValidationJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use Queueable;

    public function __construct(public int $siteId, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(AwsEdgeService $aws): void
    {
        $site = Site::query()->findOrFail($this->siteId);

        if ($site->status === Site::STATUS_PENDING_DNS_VALIDATION) {
            $dns = $aws->checkAcmDnsValidation($site);
            $site->update(['required_dns_records' => $dns['required_dns_records'] ?? $site->required_dns_records]);

            if (! ($dns['validated'] ?? false)) {
                $site->update(['status' => Site::STATUS_PENDING_DNS_VALIDATION]);
                $this->audit($site, 'acm.check_dns', 'info', $dns['message'] ?? 'DNS validation is still pending.', $dns);

                return;
            }

            $site->update([
                'status' => Site::STATUS_DEPLOYING,
                'last_error' => null,
            ]);

            Bus::chain([
                new ProvisionWafWebAclJob($site->id, $this->actorId),
                new ProvisionCloudFrontDistributionJob($site->id, $this->actorId),
                new AssociateWebAclToDistributionJob($site->id, $this->actorId),
                new MarkSiteReadyForCutoverJob($site->id, $this->actorId),
            ])->dispatch();

            $this->audit($site, 'acm.check_dns', 'success', 'Certificate validated; edge deployment started.', $dns);

            return;
        }

        if ($site->status === Site::STATUS_READY_FOR_CUTOVER || $site->status === Site::STATUS_ACTIVE) {
            $traffic = $aws->checkTrafficDns($site);
            $site->update(['required_dns_records' => $traffic['required_dns_records'] ?? $site->required_dns_records]);

            if ($traffic['validated'] ?? false) {
                $site->update([
                    'status' => Site::STATUS_ACTIVE,
                    'last_error' => null,
                    'last_provisioned_at' => now(),
                ]);

                $this->audit($site, 'traffic.check_cutover', 'success', $traffic['message'] ?? 'DNS cutover verified.', $traffic);

                return;
            }

            $site->update(['status' => Site::STATUS_READY_FOR_CUTOVER]);
            $this->audit($site, 'traffic.check_cutover', 'info', $traffic['message'] ?? 'DNS cutover still pending.', $traffic);

            return;
        }

        if ($site->status === Site::STATUS_DEPLOYING) {
            $this->audit($site, 'acm.check_dns', 'info', 'Edge deployment is still in progress.', []);

            return;
        }

        $this->audit($site, 'acm.check_dns', 'info', 'Site is not ready for DNS validation yet.', []);
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
