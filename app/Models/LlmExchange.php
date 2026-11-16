<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmExchange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'reading_session_id',
        'reading_log_id',
        'sequence',
        'method',
        'url',
        'request_body',
        'status',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class, 'reading_session_id');
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(ReadingLog::class, 'reading_log_id');
    }
}
