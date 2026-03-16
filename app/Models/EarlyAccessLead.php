<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EarlyAccessLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'company_name',
        'website_url',
        'monthly_requests_band',
        'websites_managed',
        'notes',
        'wants_launch_discount',
        'ip_address',
        'user_agent',
        'signed_up_at',
    ];

    protected function casts(): array
    {
        return [
            'wants_launch_discount' => 'boolean',
            'signed_up_at' => 'datetime',
        ];
    }
}
