<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckSiteDnsAndFinalizeProvisioningJob implements ShouldQueue
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

        if (! $site->acm_certificate_arn) {
            throw new \RuntimeException('ACM certificate is not requested yet. Click Provision first.');
        }

        try {
            $dnsCheck = $aws->checkDnsValidation($site);
            $site->update([
                'required_dns_records' => $dnsCheck['required_dns_records'] ?? $site->required_dns_records,
            ]);

            if (! ($dnsCheck['validated'] ?? false)) {
                $site->update(['status' => 'pending_dns']);

                $this->audit(
                    $site,
                    'site.provision.check_dns',
                    'info',
                    $dnsCheck['message'] ?? 'DNS validation pending.',
                    $dnsCheck,
                );

                return;
            }

            $site->update(['status' => 'provisioning']);

            $result = $aws->provisionEdge($site);

            $site->update([
                'cloudfront_distribution_id' => $result['distribution_id'] ?? $site->cloudfront_distribution_id,
                'cloudfront_domain_name' => $result['distribution_domain_name'] ?? $site->cloudfront_domain_name,
                'waf_web_acl_arn' => $result['waf_web_acl_arn'] ?? $site->waf_web_acl_arn,
                'required_dns_records' => $result['required_dns_records'] ?? $site->required_dns_records,
                'status' => 'active',
                'last_error' => null,
                'last_provisioned_at' => now(),
            ]);

            $this->audit(
                $site,
                'site.provision.finalize',
                'success',
                $result['message'] ?? 'Provisioning completed.',
                $result,
            );
        } catch (\Throwable $e) {
            $site->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            $this->audit($site, 'site.provision.finalize', 'failed', $e->getMessage(), []);

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
