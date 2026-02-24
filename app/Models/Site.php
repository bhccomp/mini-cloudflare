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
        'display_name',
        'apex_domain',
        'www_enabled',
        'www_domain',
        'origin_type',
        'origin_url',
        'origin_host',
        'status',
        'last_error',
        'last_provisioned_at',
        'acm_certificate_arn',
        'cloudfront_distribution_id',
        'cloudfront_domain_name',
        'waf_web_acl_arn',
        'required_dns_records',
        'under_attack',
    ];

    protected static function booted(): void
    {
        static::saving(function (Site $site): void {
            if (! $site->display_name && $site->name) {
                $site->display_name = $site->name;
            }

            if (! $site->name && $site->display_name) {
                $site->name = $site->display_name;
            }

            if ($site->www_enabled && ! $site->www_domain) {
                $site->www_domain = 'www.'.$site->apex_domain;
            }

            if (! $site->www_enabled) {
                $site->www_domain = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'www_enabled' => 'boolean',
            'required_dns_records' => 'array',
            'under_attack' => 'boolean',
            'last_provisioned_at' => 'datetime',
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
