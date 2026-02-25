<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_DNS_VALIDATION = 'pending_dns_validation';

    public const STATUS_DEPLOYING = 'deploying';

    public const STATUS_READY_FOR_CUTOVER = 'ready_for_cutover';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const PROVIDER_AWS = 'aws';

    public const PROVIDER_BUNNY = 'bunny';

    protected $fillable = [
        'organization_id',
        'name',
        'display_name',
        'apex_domain',
        'provider',
        'provider_resource_id',
        'provider_meta',
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

            if (! $site->provider) {
                $site->provider = (string) config('edge.default_provider', self::PROVIDER_AWS);
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
            'provider_meta' => 'array',
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

    public function analyticsMetric(): HasOne
    {
        return $this->hasOne(SiteAnalyticsMetric::class);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_DNS_VALIDATION => 'Pending DNS Validation',
            self::STATUS_DEPLOYING => 'Deploying',
            self::STATUS_READY_FOR_CUTOVER => 'Ready for Cutover',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_FAILED => 'Failed',
        ];
    }

    public static function providers(): array
    {
        return [
            self::PROVIDER_AWS => 'AWS',
            self::PROVIDER_BUNNY => 'Bunny.net',
        ];
    }
}
