<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Analytics\AnalyticsSyncManager;
use App\Services\Billing\SiteUsageMeteringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class SyncSiteAnalyticsMetricJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public int $siteId)
    {
        $this->onQueue('default');
    }

    public function handle(AnalyticsSyncManager $analytics, SiteUsageMeteringService $usageMetering): void
    {
        $site = Site::query()->find($this->siteId);

        if (! $site || $site->isDemoSeeded()) {
            return;
        }

        try {
            $snapshot = $analytics->syncSiteMetrics($site);

            if ($snapshot) {
                try {
                    $usageMetering->syncCurrentMonthOverageToStripe($site);
                } catch (\Throwable $usageException) {
                    report($usageException);
                }
            }

            AuditLog::create([
                'actor_id' => null,
                'organization_id' => $site->organization_id,
                'site_id' => $site->id,
                'action' => 'analytics.sync',
                'status' => $snapshot ? 'success' : 'info',
                'message' => $snapshot ? 'Site analytics synchronized.' : 'Site not ready for analytics sync.',
                'meta' => $snapshot ? ['captured_at' => optional($snapshot->captured_at)->toIso8601String()] : [],
            ]);
        } catch (\Throwable $e) {
            AuditLog::create([
                'actor_id' => null,
                'organization_id' => $site->organization_id,
                'site_id' => $site->id,
                'action' => 'analytics.sync',
                'status' => 'failed',
                'message' => $e->getMessage(),
                'meta' => [],
            ]);

            throw $e;
        }
    }
}
