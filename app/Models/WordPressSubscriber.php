<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressSubscriber extends Model
{
    protected $table = 'wordpress_subscribers';

    protected $fillable = [
        'email',
        'site_host',
        'home_url',
        'site_url',
        'admin_email',
        'plugin_version',
        'marketing_opt_in',
        'token_hash',
        'token_encrypted',
        'status_token_hash',
        'verification_token_hash',
        'status',
        'last_token_issued_at',
        'last_seen_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean',
            'last_token_issued_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
