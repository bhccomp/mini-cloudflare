<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'event_at',
        'ip',
        'country',
        'method',
        'host',
        'path',
        'status_code',
        'action',
        'rule',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
