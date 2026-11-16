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
        'category',
        'dimension',
        'cards',
        'llm_session_id',
        'llm_reply_id',
        'feedback_rating',
        'feedback_verdict',
        'feedback_comment',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
            'feedback_rating' => 'integer',
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

    public function exchanges(): HasMany
    {
        return $this->hasMany(LlmExchange::class)->orderBy('sequence');
    }
}
