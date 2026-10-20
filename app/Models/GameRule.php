<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRule extends Model
{
    public const INFO_ASTRO = 'astro';

    public const INFO_MEANING = 'meaning';

    public const INFO_NONE = 'none';

    /** @var list<string> */
    public const INFO_MODES = [
        self::INFO_ASTRO,
        self::INFO_MEANING,
        self::INFO_NONE,
    ];

    protected $fillable = [
        'game_id',
        'pack_slug',
        'pack_type',
        'default_pack_id',
        'title_hu',
        'title_en',
        'slot_key',
        'description_hu',
        'description_en',
        'card_back_id',
        'info_mode',
        'row',
        'place',
        'draw_order',
    ];

    protected function casts(): array
    {
        return [
            'row' => 'integer',
            'place' => 'integer',
            'draw_order' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function defaultPack(): BelongsTo
    {
        return $this->belongsTo(CardPack::class, 'default_pack_id');
    }

    public function cardBack(): BelongsTo
    {
        return $this->belongsTo(CardPack::class, 'card_back_id');
    }

    public function title(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'en' ? $this->title_en : $this->title_hu;
    }

    /**
     * @param  Builder<GameRule>  $query
     * @return Builder<GameRule>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('row')->orderBy('place')->orderBy('id');
    }
}
