<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'apex_domain',
        'environment',
        'status',
        'notes',
        'origin_url',
        'origin_ip',
        'provisioning_status',
        'cloudfront_distribution_id',
        'cloudfront_domain_name',
        'waf_web_acl_arn',
        'under_attack_mode_enabled',
        'last_provisioned_at',
        'last_provision_error',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'under_attack_mode_enabled' => 'boolean',
            'last_provisioned_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }

    public function alertChannels(): HasMany
    {
        return $this->hasMany(AlertChannel::class);
    }

    public function alertEvents(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SiteEvent::class);
    }
}
