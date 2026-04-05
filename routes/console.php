<?php

use App\Jobs\SyncSiteAnalyticsMetricJob;
use App\Models\Organization;
use App\Models\Site;
use App\Services\AvailabilityMonitorService;
use App\Services\Sites\SiteRoutingDriftMonitorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('metrics:sync-sites', function () {
    $count = 0;

    Site::query()
        ->whereNotNull('cloudfront_distribution_id')
        ->where(function ($query): void {
            $query->whereNull('provider_meta->demo_seeded')
                ->orWhere('provider_meta->demo_seeded', false);
        })
        ->whereIn('status', [
            Site::STATUS_DEPLOYING,
            Site::STATUS_READY_FOR_CUTOVER,
            Site::STATUS_ACTIVE,
        ])
        ->orderBy('id')
        ->chunkById(100, function ($sites) use (&$count): void {
            foreach ($sites as $site) {
                SyncSiteAnalyticsMetricJob::dispatch($site->id);
                $count++;
            }
        });

    $this->info("Queued analytics sync for {$count} site(s).");
})->purpose('Queue analytics synchronization for provider-managed sites.');

Schedule::command('metrics:sync-sites')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Artisan::command('availability:run-due', function (AvailabilityMonitorService $monitor): void {
    if (! Schema::hasTable('site_availability_checks')) {
        $this->warn('Skipping availability checks because migration is not applied yet.');

        return;
    }

    $count = 0;

    Organization::query()
        ->whereHas('sites')
        ->orderBy('id')
        ->chunkById(100, function ($organizations) use ($monitor, &$count): void {
            foreach ($organizations as $organization) {
                $count += $monitor->runDueChecksForOrganization($organization);
            }
        });

    $this->info("Availability checks executed for {$count} site(s).");
})->purpose('Run due availability checks based on plan cadence.');

Schedule::command('availability:run-due')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(function (SiteRoutingDriftMonitorService $monitor): void {
    $monitor->run();
})
    ->name('sites:monitor-routing-drift')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('demo:seed-dashboard')
    ->cron((string) config('demo.refresh_cron', '0 */6 * * *'))
    ->withoutOverlapping();

Schedule::command('demo:cleanup-dashboard')
    ->cron((string) config('demo.cleanup_cron', '30 3 * * *'))
    ->withoutOverlapping();
