<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'included_websites',
        'included_requests_per_month',
        'overage_block_size',
        'overage_price_cents',
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
        'stripe_request_meter_id',
        'stripe_request_overage_price_id',
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

    public function hasRequestOverageBilling(): bool
    {
        return $this->included_requests_per_month > 0
            && $this->overage_block_size > 0
            && $this->overage_price_cents > 0;
    }

    public function includedWebsites(): int
    {
        return max(1, (int) $this->included_websites);
    }

    public function displayIncludedRequests(): string
    {
        return number_format((int) $this->included_requests_per_month);
    }

    public function displayOverageRate(): string
    {
        if (! $this->hasRequestOverageBilling()) {
            return 'Not configured';
        }

        return '$'.number_format($this->overage_price_cents / 100, 2).' / '.number_format((int) $this->overage_block_size).' requests';
    }

    public function displayPrice(): string
    {
        if ($this->is_contact_only) {
            return 'Custom';
        }

        $amount = ((int) $this->monthly_price_cents) / 100;
        $precision = fmod($amount, 1.0) === 0.0 ? 0 : 2;

        return '$'.number_format($amount, $precision);
    }

    public function displayFeatures(): array
    {
        return array_values(array_filter((array) $this->features, fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
    }

    public function displayLimits(): array
    {
        return collect((array) $this->limits)
            ->filter(function (mixed $value, mixed $key): bool {
                return trim((string) $key) !== '' && ! in_array($value, [null, ''], true);
            })
            ->map(function (mixed $value, mixed $key): string {
                $label = Str::of((string) $key)
                    ->replace(['_', '-'], ' ')
                    ->headline()
                    ->toString();

                if (is_bool($value)) {
                    return $value ? $label : '';
                }

                if (is_array($value)) {
                    $value = implode(', ', array_filter(array_map('strval', $value)));
                }

                return sprintf('%s: %s', $label, (string) $value);
            })
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->values()
            ->all();
    }
}
