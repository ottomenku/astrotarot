<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingTurn extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'reading_session_id',
        'sequence',
        'role',
        'kind',
        'content',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class, 'reading_session_id');
    }
}
