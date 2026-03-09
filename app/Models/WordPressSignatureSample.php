<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressSignatureSample extends Model
{
    protected $fillable = [
        'name',
        'sample_type',
        'family',
        'language',
        'notes',
        'file_path',
        'original_filename',
        'content',
        'sha256',
        'size_bytes',
        'signals',
    ];

    protected function casts(): array
    {
        return [
            'signals' => 'array',
        ];
    }
}
