<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAnalyticsMetric extends Model
{
    protected $fillable = [
        'site_id',
        'blocked_requests_24h',
        'allowed_requests_24h',
        'total_requests_24h',
        'cache_hit_ratio',
        'cached_requests_24h',
        'origin_requests_24h',
        'trend_labels',
        'blocked_trend',
        'allowed_trend',
        'regional_traffic',
        'regional_threat',
        'source',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'trend_labels' => 'array',
            'blocked_trend' => 'array',
            'allowed_trend' => 'array',
            'regional_traffic' => 'array',
            'regional_threat' => 'array',
            'source' => 'array',
            'captured_at' => 'datetime',
            'cache_hit_ratio' => 'decimal:2',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
