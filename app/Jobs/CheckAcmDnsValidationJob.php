<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckAcmDnsValidationJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(public int $siteId, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(AwsEdgeService $aws): void
    {
        $site = Site::query()->findOrFail($this->siteId);

        $dns = $aws->checkAcmDnsValidation($site);
        $site->update(['required_dns_records' => $dns['required_dns_records'] ?? $site->required_dns_records]);

        if (! ($dns['validated'] ?? false)) {
            $site->update(['status' => 'pending_dns']);
            $this->audit($site, 'acm.check_dns', 'info', $dns['message'] ?? 'ACM DNS pending.', $dns);

            return;
        }

        if (! $site->cloudfront_distribution_id) {
            $site->update(['status' => 'provisioning']);
            ProvisionCloudFrontDistributionJob::dispatch($site->id, $this->actorId);
            ProvisionWafWebAclJob::dispatch($site->id, $this->actorId);
            AssociateWebAclToDistributionJob::dispatch($site->id, $this->actorId);

            $this->audit($site, 'acm.check_dns', 'success', 'ACM DNS validated; provisioning queued.', $dns);

            return;
        }

        $traffic = $aws->checkTrafficDns($site);
        $site->update(['required_dns_records' => $traffic['required_dns_records'] ?? $site->required_dns_records]);

        if ($traffic['validated'] ?? false) {
            $site->update(['status' => 'active', 'last_error' => null, 'last_provisioned_at' => now()]);
            $this->audit($site, 'traffic.check_dns', 'success', $traffic['message'] ?? 'Traffic DNS validated.', $traffic);

            return;
        }

        $site->update(['status' => 'provisioning']);
        $this->audit($site, 'traffic.check_dns', 'info', $traffic['message'] ?? 'Traffic DNS pending.', $traffic);
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
