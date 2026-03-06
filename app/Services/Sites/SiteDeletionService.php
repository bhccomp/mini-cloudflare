<?php

namespace App\Services\Sites;

use App\Models\AlertChannel;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SiteDeletionService
{
    /**
     * @return array<string, int>
     */
    public function deleteSite(Site $site): array
    {
        return DB::transaction(function () use ($site): array {
            $counts = [
                'selected_site_refs' => User::query()->where('selected_site_id', $site->id)->update(['selected_site_id' => null]),
                'audit_logs' => AuditLog::query()->where('site_id', $site->id)->delete(),
                'alert_channels' => AlertChannel::query()->where('site_id', $site->id)->delete(),
                'availability_checks' => $site->availabilityChecks()->delete(),
                'edge_request_logs' => $site->edgeRequestLogs()->delete(),
                'alert_events' => $site->alertEvents()->delete(),
                'alert_rules' => $site->alertRules()->delete(),
                'site_events' => $site->events()->delete(),
                'firewall_rules' => $site->firewallRules()->delete(),
                'analytics_metrics' => $site->analyticsMetric()->delete(),
            ];

            $site->delete();

            $counts['site'] = 1;

            return $counts;
        });
    }
}
