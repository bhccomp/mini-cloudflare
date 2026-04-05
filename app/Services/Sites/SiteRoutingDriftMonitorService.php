<?php

namespace App\Services\Sites;

use App\Models\Site;
use App\Notifications\SiteRoutingDriftNotification;
use Illuminate\Support\Carbon;

class SiteRoutingDriftMonitorService
{
    /**
     * @return array{checked:int,started:int,notified:int,resolved:int}
     */
    public function run(): array
    {
        $summary = [
            'checked' => 0,
            'started' => 0,
            'notified' => 0,
            'resolved' => 0,
        ];

        Site::query()
            ->where(function ($query): void {
                $query->where('status', Site::STATUS_ACTIVE)
                    ->orWhere('onboarding_status', Site::ONBOARDING_LIVE);
            })
            ->where(function ($query): void {
                $query->whereNull('provider_meta->demo_seeded')
                    ->orWhere('provider_meta->demo_seeded', false);
            })
            ->with('organization.users')
            ->orderBy('id')
            ->chunkById(100, function ($sites) use (&$summary): void {
                foreach ($sites as $site) {
                    $summary['checked']++;

                    $status = app(SiteRoutingStatusService::class)->statusForSite($site, true);
                    $routingState = (string) ($status['status'] ?? '');

                    if (in_array($routingState, ['drift', 'partial'], true)) {
                        $result = $this->handleDriftedSite($site, $status);
                        $summary['started'] += $result['started'];
                        $summary['notified'] += $result['notified'];

                        continue;
                    }

                    if ($this->clearDriftState($site)) {
                        $summary['resolved']++;
                    }
                }
            });

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{started:int,notified:int}
     */
    protected function handleDriftedSite(Site $site, array $status): array
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $startedAt = data_get($meta, 'routing_drift_started_at');
        $notifiedAt = data_get($meta, 'routing_drift_notified_at');

        if (! $startedAt) {
            $meta['routing_drift_started_at'] = now()->toIso8601String();
            unset($meta['routing_drift_notified_at'], $meta['routing_drift_last_error']);
            $site->forceFill(['provider_meta' => $meta])->save();

            return ['started' => 1, 'notified' => 0];
        }

        if ($notifiedAt) {
            return ['started' => 0, 'notified' => 0];
        }

        $startedAtTime = Carbon::parse((string) $startedAt);
        if ($startedAtTime->gt(now()->subHours(5))) {
            return ['started' => 0, 'notified' => 0];
        }

        if ($this->isSuppressedForCurrentIncident($meta, (string) $startedAt)) {
            return ['started' => 0, 'notified' => 0];
        }

        $users = $site->organization?->users ?? collect();
        if ($users->isEmpty()) {
            $meta['routing_drift_last_error'] = 'No organization members found for routing drift notification.';
            $site->forceFill(['provider_meta' => $meta])->save();

            return ['started' => 0, 'notified' => 0];
        }

        foreach ($users as $user) {
            $user->notify(new SiteRoutingDriftNotification($site, $status));
        }

        $meta['routing_drift_notified_at'] = now()->toIso8601String();
        unset($meta['routing_drift_last_error']);
        $site->forceFill(['provider_meta' => $meta])->save();

        return ['started' => 0, 'notified' => 1];
    }

    protected function clearDriftState(Site $site): bool
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];

        if (
            ! array_key_exists('routing_drift_started_at', $meta)
            && ! array_key_exists('routing_drift_notified_at', $meta)
            && ! array_key_exists('routing_drift_last_error', $meta)
            && ! array_key_exists('routing_drift_suppressed_started_at', $meta)
        ) {
            return false;
        }

        unset(
            $meta['routing_drift_started_at'],
            $meta['routing_drift_notified_at'],
            $meta['routing_drift_last_error'],
            $meta['routing_drift_suppressed_started_at'],
            $meta['routing_drift_suppressed_at'],
            $meta['routing_drift_suppressed_note']
        );
        $meta['routing_drift_last_resolved_at'] = now()->toIso8601String();

        $site->forceFill(['provider_meta' => $meta])->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function isSuppressedForCurrentIncident(array $meta, string $startedAt): bool
    {
        $suppressedStartedAt = (string) data_get($meta, 'routing_drift_suppressed_started_at', '');

        return $suppressedStartedAt !== '' && $suppressedStartedAt === $startedAt;
    }
}
