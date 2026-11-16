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

    public const LIGHT_SHADOW_NONE = 'none';

    public const LIGHT_SHADOW_RANDOM = 'random';

    public const LIGHT_SHADOW_RANDOM60 = 'random60';

    public const LIGHT_SHADOW_RANDOM70 = 'random70';

    public const LIGHT_SHADOW_ASTRO = 'astro';

    public const LIGHT_SHADOW_LLM = 'llm';

    /** @var list<string> */
    public const LIGHT_SHADOW_VALUES = [
        self::LIGHT_SHADOW_NONE,
        self::LIGHT_SHADOW_RANDOM,
        self::LIGHT_SHADOW_RANDOM60,
        self::LIGHT_SHADOW_RANDOM70,
        self::LIGHT_SHADOW_ASTRO,
        self::LIGHT_SHADOW_LLM,
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
        'celestial_helper',
        'light_shadow',
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

    /**
     * A stored slot value wins. An empty value keeps the older spread-level mode.
     */
    public function effectiveLightShadow(?Game $game): string
    {
        $mode = (string) ($this->light_shadow ?? '');
        if (in_array($mode, self::LIGHT_SHADOW_VALUES, true)) {
            return $mode;
        }

        if ($game !== null && $game->requestsCelestialLightShadow((string) $this->pack_type)) {
            return self::LIGHT_SHADOW_LLM;
        }

        return self::LIGHT_SHADOW_RANDOM;
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
