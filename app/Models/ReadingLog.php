<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingLog extends Model
{
    protected $fillable = [
        'user_id',
        'locale',
        'question',
        'cards',
        'astro',
        'system_prompt',
        'user_prompt',
        'response',
        'model',
        'status',
        'error',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
            'astro' => 'array',
        ];
    }
}
