<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'headline',
        'description',
        'monthly_price_cents',
        'yearly_price_cents',
        'price_suffix',
        'badge',
        'cta_label',
        'is_featured',
        'is_contact_only',
        'show_on_marketing_site',
        'sort_order',
        'currency',
        'stripe_product_id',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'stripe_synced_at',
        'limits',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_contact_only' => 'boolean',
            'show_on_marketing_site' => 'boolean',
            'stripe_synced_at' => 'datetime',
        ];
    }

    public function displayPrice(): string
    {
        if ($this->is_contact_only) {
            return 'Custom';
        }

        return '$'.number_format($this->monthly_price_cents / 100, 0);
    }

    public function displayFeatures(): array
    {
        return array_values(array_filter((array) $this->features, fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
    }
}
