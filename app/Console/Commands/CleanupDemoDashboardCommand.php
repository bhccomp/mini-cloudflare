<?php

namespace App\Console\Commands;

use App\Models\EdgeRequestLog;
use App\Models\OrganizationSubscription;
use App\Models\PluginSiteConnection;
use App\Models\Site;
use App\Models\SiteAvailabilityCheck;
use App\Models\SiteAnalyticsMetric;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CleanupDemoDashboardCommand extends Command
{
    protected $signature = 'demo:cleanup-dashboard';

    protected $description = 'Delete stale demo-seeded telemetry older than the retention window.';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subDays(14);

        $siteIds = Site::query()
            ->get(['id', 'provider_meta'])
            ->filter(fn (Site $site): bool => $site->isDemoSeeded())
            ->pluck('id');

        if ($siteIds->isEmpty()) {
            $this->info('No demo-seeded sites found.');

            return self::SUCCESS;
        }

        $deletedLogs = EdgeRequestLog::query()
            ->whereIn('site_id', $siteIds)
            ->where('event_at', '<', $cutoff)
            ->delete();

        $deletedChecks = SiteAvailabilityCheck::query()
            ->whereIn('site_id', $siteIds)
            ->where('checked_at', '<', $cutoff)
            ->delete();

        $deletedSubscriptions = OrganizationSubscription::query()
            ->where('meta->demo_seeded', true)
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $deletedMetrics = SiteAnalyticsMetric::query()
            ->whereIn('site_id', $siteIds)
            ->where('captured_at', '<', $cutoff)
            ->delete();

        PluginSiteConnection::query()
            ->whereIn('site_id', $siteIds)
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_seen_at', '<', $cutoff)
                    ->orWhere('last_reported_at', '<', $cutoff);
            })
            ->delete();

        $this->info(sprintf(
            'Demo cleanup complete. Logs: %d, checks: %d, subscriptions: %d, metrics: %d',
            $deletedLogs,
            $deletedChecks,
            $deletedSubscriptions,
            $deletedMetrics,
        ));

        return self::SUCCESS;
    }
}
