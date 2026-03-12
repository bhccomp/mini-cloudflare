<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressRepoSyncHash extends Model
{
    protected $table = 'wordpress_repo_sync_hashes';

    protected $fillable = [
        'algorithm',
        'hash_value',
        'status',
        'source',
        'notes',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
