<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteAvailabilityCheck;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AvailabilityMonitorService
{
    public function intervalSecondsForSite(Site $site): int
    {
        $site->loadMissing(['organization.subscriptions.plan']);

        $planCode = strtolower((string) (
            $site->organization?->subscriptions
                ?->first(fn ($sub): bool => in_array((string) $sub->status, ['active', 'trialing'], true))
                ?->plan?->code
            ?? 'basic'
        ));

        $paidPlans = (array) config('ui.availability_monitor_paid_plans', ['pro', 'business', 'enterprise']);
        $isPaid = in_array($planCode, $paidPlans, true);

        return $isPaid
            ? (int) config('ui.availability_monitor_intervals.paid', 60)
            : (int) config('ui.availability_monitor_intervals.basic', 300);
    }

    public function intervalLabelForSite(Site $site): string
    {
        $seconds = $this->intervalSecondsForSite($site);

        return $seconds <= 60 ? '1 minute' : '5 minutes';
    }

    public function runCheck(Site $site): SiteAvailabilityCheck
    {
        $startedAt = microtime(true);
        $target = 'https://'.$site->apex_domain.'/';

        try {
            $response = Http::timeout(15)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'FirePhage-Availability-Monitor/1.0'])
                ->get($target);

            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $status = $response->successful() || in_array($response->status(), [301, 302, 307, 308], true)
                ? 'up'
                : 'down';

            return SiteAvailabilityCheck::query()->create([
                'site_id' => $site->id,
                'checked_at' => now(),
                'status' => $status,
                'status_code' => $response->status(),
                'latency_ms' => $latency,
                'error_message' => null,
                'meta' => [
                    'target' => $target,
                ],
            ]);
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);

            return SiteAvailabilityCheck::query()->create([
                'site_id' => $site->id,
                'checked_at' => now(),
                'status' => 'down',
                'status_code' => null,
                'latency_ms' => $latency,
                'error_message' => substr($e->getMessage(), 0, 250),
                'meta' => [
                    'target' => $target,
                ],
            ]);
        }
    }

    public function runDueChecksForOrganization(Organization $organization): int
    {
        $count = 0;

        Site::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->chunkById(100, function (Collection $sites) use (&$count): void {
                foreach ($sites as $site) {
                    if (! $site instanceof Site) {
                        continue;
                    }

                    if (! $this->isDue($site)) {
                        continue;
                    }

                    $this->runCheck($site);
                    $count++;
                }
            });

        return $count;
    }

    public function isDue(Site $site): bool
    {
        $lastCheck = $site->availabilityChecks()->latest('checked_at')->first();

        if (! $lastCheck?->checked_at) {
            return true;
        }

        return $lastCheck->checked_at->lte(now()->subSeconds($this->intervalSecondsForSite($site)));
    }
}
