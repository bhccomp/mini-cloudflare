<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RequestAcmCertificateJob implements ShouldQueue
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

        if (
            $provider->requiresCertificateValidation()
            && $site->status === Site::STATUS_ACTIVE
            && $site->acm_certificate_arn
        ) {
            $this->audit($site, 'acm.request', 'info', 'Certificate already exists.', ['provider' => $provider->key()]);

            return;
        }

        try {
            $result = $provider->requestCertificate($site);

            $site->update([
                'provider' => $site->provider ?: $provider->key(),
                'required_dns_records' => $result['required_dns_records'] ?? $site->required_dns_records,
                'acm_certificate_arn' => $result['certificate_arn'] ?? $site->acm_certificate_arn,
                'status' => Site::STATUS_PENDING_DNS_VALIDATION,
                'onboarding_status' => Site::ONBOARDING_PENDING_DNS_VALIDATION,
                'last_error' => null,
            ]);

            $this->audit($site, 'acm.request', 'success', $result['message'] ?? 'Edge provisioning request submitted.', $result + ['provider' => $provider->key()]);
        } catch (\Throwable $e) {
            $site->update(['status' => Site::STATUS_FAILED, 'last_error' => $e->getMessage()]);
            $this->audit($site, 'acm.request', 'failed', $e->getMessage(), ['provider' => $provider->key()]);
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
