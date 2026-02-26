<?php

namespace App\Filament\App\Pages;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Bunny\BunnyLogsService;

class CachePage extends BaseProtectionPage
{
    protected static ?string $slug = 'cache';

    protected static ?int $navigationSort = 1;

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

        if ($this->site->provider !== Site::PROVIDER_BUNNY) {
            return [];
        }

        $rows = app(BunnyLogsService::class)->recentLogs($this->site, 300);

        return collect($rows)
            ->filter(fn (array $row): bool => (int) ($row['status_code'] ?? 200) >= 400)
            ->groupBy(fn (array $row): string => (string) ($row['uri'] ?? '/'))
            ->map(fn ($group, string $path): array => ['path' => $path, 'misses' => $group->count()])
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
