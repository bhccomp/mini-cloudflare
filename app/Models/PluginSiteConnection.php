<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginSiteConnection extends Model
{
    protected $fillable = [
        'site_id',
        'site_token_hash',
        'status',
        'home_url',
        'site_url',
        'admin_email',
        'plugin_version',
        'last_report_payload',
        'last_connected_at',
        'last_seen_at',
        'last_reported_at',
    ];

    protected function casts(): array
    {
        return [
            'last_report_payload' => 'array',
            'last_connected_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_reported_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
