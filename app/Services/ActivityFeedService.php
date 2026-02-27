<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EdgeRequestLog;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ActivityFeedService
{
    /**
     * @return array<int, array{message: string, at: Carbon|null}>
     */
    public function forSite(Site $site, int $limit = 5): array
    {
        return Cache::remember(
            "activity-feed:site:{$site->id}:{$limit}",
            now()->addSeconds(45),
            fn (): array => $this->buildFeed($site, $limit),
        );
    }

    /**
     * @return array<int, array{message: string, at: Carbon|null}>
     */
    protected function buildFeed(Site $site, int $limit): array
    {
        $since = now()->subDay();
        $items = [];

        $blockedCount = EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->where('event_at', '>=', $since)
            ->where(function ($query): void {
                $query->whereIn('action', ['BLOCK', 'DENY', 'CHALLENGE'])
                    ->orWhere('status_code', '>=', 400);
            })
            ->count();

        $blockedIps = EdgeRequestLog::query()
            ->where('site_id', $site->id)
            ->where('event_at', '>=', $since)
            ->where(function ($query): void {
                $query->whereIn('action', ['BLOCK', 'DENY', 'CHALLENGE'])
                    ->orWhere('status_code', '>=', 400);
            })
            ->distinct('ip')
            ->count('ip');

        if ($blockedCount > 0) {
            $items[] = [
                'message' => 'Blocked '.number_format($blockedCount).' suspicious requests from '.number_format($blockedIps).' IPs.',
                'at' => EdgeRequestLog::query()
                    ->where('site_id', $site->id)
                    ->latest('event_at')
                    ->value('event_at'),
            ];
        }

        $topCountry = EdgeRequestLog::query()
            ->selectRaw('country, COUNT(*) as total')
            ->where('site_id', $site->id)
            ->where('event_at', '>=', now()->subHours(6))
            ->groupBy('country')
            ->orderByDesc('total')
            ->first();

        if ($topCountry && (int) $topCountry->total > 0) {
            $items[] = [
                'message' => 'Traffic spike detected from '.strtoupper((string) $topCountry->country).' ('.number_format((int) $topCountry->total).' requests).',
                'at' => now(),
            ];
        }

        $recentProtectionAction = AuditLog::query()
            ->where('site_id', $site->id)
            ->where(function ($query): void {
                $query->where('action', 'like', 'waf.%')
                    ->orWhere('action', 'like', 'edge.%')
                    ->orWhere('action', 'like', 'cloudfront.%');
            })
            ->latest('id')
            ->first();

        if ($recentProtectionAction) {
            $items[] = [
                'message' => 'Protection workflow updated: '.($recentProtectionAction->message ?: 'Policy change applied.'),
                'at' => $recentProtectionAction->created_at,
            ];
        }

        if ($items === []) {
            $items[] = [
                'message' => 'No major events yet. As traffic flows, this feed will summarize protection activity.',
                'at' => null,
            ];
        }

        return collect($items)
            ->sortByDesc(fn (array $item) => $item['at']?->timestamp ?? 0)
            ->take($limit)
            ->values()
            ->all();
    }
}
