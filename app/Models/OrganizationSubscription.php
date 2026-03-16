<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrganizationSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'site_id',
        'plan_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'status',
        'renews_at',
        'ends_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(
            Site::class,
            'organization_subscription_site',
            'organization_subscription_id',
            'site_id',
        )->withTimestamps();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function includedWebsiteSlots(): int
    {
        return $this->plan?->includedWebsites() ?? 1;
    }

    public function assignableSiteCount(): int
    {
        $loadedSiteIds = $this->relationLoaded('sites')
            ? $this->sites->pluck('id')->all()
            : $this->sites()->pluck('sites.id')->all();

        if ($this->site_id) {
            $loadedSiteIds[] = (int) $this->site_id;
        }

        return count(array_unique(array_map('intval', $loadedSiteIds)));
    }
}
