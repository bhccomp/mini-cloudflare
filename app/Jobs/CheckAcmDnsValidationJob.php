<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
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

    public function handle(EdgeProviderManager $providers): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        $provider = $providers->forSite($site);
        $wasLive = $site->onboarding_status === Site::ONBOARDING_LIVE;

        if ($site->status === Site::STATUS_PENDING_DNS_VALIDATION) {
            $dns = $provider->checkCertificateValidation($site);
            $site->update([
                'required_dns_records' => $dns['required_dns_records'] ?? $site->required_dns_records,
                'last_checked_at' => now(),
            ]);

            if (! ($dns['validated'] ?? false)) {
                $site->update([
                    'status' => Site::STATUS_PENDING_DNS_VALIDATION,
                    'onboarding_status' => Site::ONBOARDING_PENDING_DNS_VALIDATION,
                ]);
                $this->audit($site, 'acm.check_dns', 'info', $dns['message'] ?? 'DNS validation is still pending.', $dns + ['provider' => $provider->key()]);

                return;
            }

            $site->update([
                'status' => Site::STATUS_DEPLOYING,
                'onboarding_status' => Site::ONBOARDING_PROVISIONING_EDGE,
                'last_error' => null,
            ]);

            Bus::chain([
                new ProvisionEdgeDeploymentJob($site->id, $this->actorId),
                new MarkSiteReadyForCutoverJob($site->id, $this->actorId),
            ])->dispatch();

            $this->audit($site, 'acm.check_dns', 'success', 'Validation complete; edge deployment started.', $dns + ['provider' => $provider->key()]);

            return;
        }

        if ($site->status === Site::STATUS_READY_FOR_CUTOVER || $site->status === Site::STATUS_ACTIVE) {
            $traffic = $provider->checkDns($site);
            $site->update([
                'required_dns_records' => $traffic['required_dns_records'] ?? $site->required_dns_records,
                'last_checked_at' => now(),
            ]);

            if ($traffic['validated'] ?? false) {
                if ($provider->key() === Site::PROVIDER_BUNNY) {
                    $ssl = $provider->checkSsl($site->fresh());

                    if (($ssl['status'] ?? 'pending') === 'active') {
                        $site->update([
                            'status' => Site::STATUS_ACTIVE,
                            'onboarding_status' => Site::ONBOARDING_LIVE,
                            'last_error' => null,
                            'last_provisioned_at' => now(),
                        ]);

                        $purgeMeta = $this->purgeCacheAfterBunnyCutover($site->fresh(), $provider, $wasLive);

                        $this->audit(
                            $site,
                            'traffic.check_cutover',
                            'success',
                            $ssl['message'] ?? 'DNS and SSL verification complete.',
                            $traffic + ['provider' => $provider->key(), 'ssl' => $ssl] + $purgeMeta
                        );

                        return;
                    }

                    if (($ssl['status'] ?? 'pending') === 'error') {
                        $site->update([
                            'status' => Site::STATUS_FAILED,
                            'onboarding_status' => Site::ONBOARDING_FAILED,
                            'last_error' => $ssl['message'] ?? 'SSL check failed.',
                        ]);

                        $this->audit(
                            $site,
                            'traffic.check_cutover',
                            'failed',
                            $ssl['message'] ?? 'SSL check failed.',
                            $traffic + ['provider' => $provider->key(), 'ssl' => $ssl]
                        );

                        return;
                    }

                    $site->update([
                        'status' => Site::STATUS_READY_FOR_CUTOVER,
                        'onboarding_status' => Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING,
                        'last_error' => null,
                    ]);

                    $this->audit(
                        $site,
                        'traffic.check_cutover',
                        'info',
                        $ssl['message'] ?? 'DNS is verified and SSL is still pending.',
                        $traffic + ['provider' => $provider->key(), 'ssl' => $ssl]
                    );

                    return;
                }

                $site->update([
                    'status' => Site::STATUS_ACTIVE,
                    'onboarding_status' => Site::ONBOARDING_LIVE,
                    'last_error' => null,
                    'last_provisioned_at' => now(),
                ]);

                $this->audit($site, 'traffic.check_cutover', 'success', $traffic['message'] ?? 'DNS cutover verified.', $traffic + ['provider' => $provider->key()]);

                return;
            }

            $site->update([
                'status' => Site::STATUS_READY_FOR_CUTOVER,
                'onboarding_status' => Site::ONBOARDING_PENDING_DNS_CUTOVER,
            ]);
            $this->audit($site, 'traffic.check_cutover', 'info', $traffic['message'] ?? 'DNS cutover still pending.', $traffic + ['provider' => $provider->key()]);

            return;
        }

        if ($site->status === Site::STATUS_DEPLOYING) {
            $this->audit($site, 'acm.check_dns', 'info', 'Edge deployment is still in progress.', ['provider' => $provider->key()]);

            return;
        }

        $this->audit($site, 'acm.check_dns', 'info', 'Site is not ready for DNS validation yet.', ['provider' => $provider->key()]);
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

    /**
     * @return array<string, mixed>
     */
    protected function purgeCacheAfterBunnyCutover(Site $site, mixed $provider, bool $wasLive): array
    {
        if ($provider->key() !== Site::PROVIDER_BUNNY || $wasLive) {
            return [];
        }

        if (data_get($site->provider_meta, 'initial_live_cache_purged_at')) {
            return [
                'cache_purge' => 'already-recorded',
            ];
        }

        try {
            $result = $provider->purgeCache($site, ['/*']);

            $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
            $meta['initial_live_cache_purged_at'] = now()->toIso8601String();
            $site->forceFill(['provider_meta' => $meta])->save();

            return [
                'cache_purge' => 'requested',
                'cache_purge_result' => $result,
            ];
        } catch (\Throwable $e) {
            $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
            $meta['initial_live_cache_purge_failed_at'] = now()->toIso8601String();
            $meta['initial_live_cache_purge_last_error'] = $e->getMessage();
            $site->forceFill(['provider_meta' => $meta])->save();

            $this->audit(
                $site,
                'traffic.purge_cache_after_cutover',
                'failed',
                'Automatic Bunny cache purge after cutover failed.',
                ['provider' => $provider->key(), 'error' => $e->getMessage()]
            );

            return [
                'cache_purge' => 'failed',
                'cache_purge_error' => $e->getMessage(),
            ];
        }
    }
}
