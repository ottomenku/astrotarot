<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReadingSession extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'game_id',
        'locale',
        'question',
        'cards',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReadingSession $session): void {
            if (! filled($session->public_id)) {
                $session->public_id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(ReadingTurn::class)->orderBy('sequence');
    }
}
