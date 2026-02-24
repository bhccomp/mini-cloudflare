<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsAnalyticsService;
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

    public function handle(AwsAnalyticsService $analytics): void
    {
        $site = Site::query()->find($this->siteId);

        if (! $site) {
            return;
        }

        try {
            $snapshot = $analytics->syncSiteMetrics($site);

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
