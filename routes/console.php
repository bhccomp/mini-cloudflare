<?php

use App\Jobs\SyncSiteAnalyticsMetricJob;
use App\Models\Site;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('metrics:sync-sites', function () {
    $count = 0;

    Site::query()
        ->whereNotNull('cloudfront_distribution_id')
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
