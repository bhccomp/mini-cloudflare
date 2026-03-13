<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressMaliciousString extends Model
{
    protected $table = 'wordpress_malicious_strings';

    protected $fillable = [
        'name',
        'needle',
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
