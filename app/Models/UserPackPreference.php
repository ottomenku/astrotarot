<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPackPreference extends Model
{
    public const ROLE_FIGURE = 'figure';

    public const ROLE_CARD_BACK = 'card_back';

    protected $fillable = [
        'user_id',
        'pack_type',
        'figure_pack_id',
        'role',
        'card_pack_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function figurePack(): BelongsTo
    {
        return $this->belongsTo(CardPack::class, 'figure_pack_id');
    }

    public function cardPack(): BelongsTo
    {
        return $this->belongsTo(CardPack::class, 'card_pack_id');
    }
}
