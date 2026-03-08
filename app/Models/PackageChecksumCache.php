<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageChecksumCache extends Model
{
    protected $fillable = [
        'type',
        'slug',
        'version',
        'checksums',
        'fetched_at',
        'expires_at',
        'last_error',
        'last_error_at',
    ];

    protected function casts(): array
    {
        return [
            'checksums' => 'array',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }
}
