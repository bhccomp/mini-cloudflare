<?php

namespace App\Console\Commands;

use App\Services\Sites\SiteRoutingDriftMonitorService;
use Illuminate\Console\Command;

class MonitorSiteRoutingDriftCommand extends Command
{
    protected $signature = 'sites:monitor-routing-drift';

    protected $description = 'Detect live sites that are no longer routed through FirePhage and notify organizations after a grace period.';

    public function handle(SiteRoutingDriftMonitorService $monitor): int
    {
        $summary = $monitor->run();

        $this->info(
            "Checked {$summary['checked']} live site(s); ".
            "started {$summary['started']} drift timer(s), ".
            "sent {$summary['notified']} notification batch(es), ".
            "resolved {$summary['resolved']} drift state(s)."
        );

        return self::SUCCESS;
    }
}
