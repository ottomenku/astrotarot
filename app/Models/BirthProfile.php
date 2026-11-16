<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthProfile extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'birth_at',
        'birth_country',
        'birth_city',
        'birth_latitude',
        'birth_longitude',
        'introduction',
    ];

    protected function casts(): array
    {
        return [
            'birth_at' => 'datetime',
            'birth_latitude' => 'float',
            'birth_longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
