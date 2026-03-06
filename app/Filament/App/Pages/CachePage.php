<?php

namespace App\Filament\App\Pages;

use App\Models\AuditLog;
use App\Models\EdgeRequestLog;
use App\Models\Site;

class CachePage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'Performance';

    protected static ?string $slug = 'cache';

    protected static ?int $navigationSort = -9;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Cache';

    protected static ?string $title = 'Cache';

    protected string $view = 'filament.app.pages.protection.cache';

    public function cacheHitTrend(): array
    {
        $trend = (array) ($this->site?->analyticsMetric?->trend_labels ?? []);
        $ratio = (float) ($this->site?->analyticsMetric?->cache_hit_ratio ?? 0);

        if ($trend === []) {
            return [];
        }

        return collect($trend)
            ->map(fn (string $day, int $idx): array => [
                'day' => $day,
                'hit' => max(0, min(100, (int) round($ratio - (6 - $idx) * 2 + (($idx % 2) ? 1 : -1)))),
            ])
            ->all();
    }

    public function topCacheMisses(): array
    {
        if (! $this->site) {
            return [];
        }

        return EdgeRequestLog::query()
            ->where('site_id', $this->site->id)
            ->where('event_at', '>=', now()->subDays(7))
            ->where('status_code', '>=', 400)
            ->selectRaw('path, count(*) as misses')
            ->groupBy('path')
            ->get()
            ->map(fn (EdgeRequestLog $row): array => [
                'path' => (string) ($row->path ?: '/'),
                'misses' => (int) ($row->misses ?? 0),
            ])
            ->sortByDesc('misses')
            ->take(10)
            ->values()
            ->all();
    }

    public function purgeHistory(): array
    {
        if (! $this->site) {
            return [];
        }

        return AuditLog::query()
            ->where('site_id', $this->site->id)
            ->where('action', 'cloudfront.invalidate')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (AuditLog $row): array => [
                'timestamp' => $row->created_at,
                'status' => $row->status,
                'message' => $row->message,
                'purge_id' => data_get($row->meta, 'invalidation_id', data_get($row->meta, 'purge_id', 'n/a')),
            ])
            ->all();
    }
}
