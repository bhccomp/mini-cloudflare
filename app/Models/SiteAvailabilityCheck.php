<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAvailabilityCheck extends Model
{
    protected $fillable = [
        'site_id',
        'checked_at',
        'status',
        'status_code',
        'latency_ms',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
