<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressMaliciousDomain extends Model
{
    protected $table = 'wordpress_malicious_domains';

    protected $fillable = [
        'domain',
        'status',
        'source',
        'notes',
        'last_tested_at',
        'last_test_result',
    ];

    protected function casts(): array
    {
        return [
            'last_tested_at' => 'datetime',
            'last_test_result' => 'array',
        ];
    }
}
